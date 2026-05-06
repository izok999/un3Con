<?php

use App\Models\User;
use App\Services\AlumnoExternoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Role;

new #[Layout('layouts.app')] class extends Component
{
    public string $documento = '';

    public function mount(): void
    {
        $user = Auth::user();

        if (! $user) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        if (filled($user->documento)) {
            $this->redirectIntended(default: $this->defaultRedirect(), navigate: true);
        }
    }

    public function linkDocumento(AlumnoExternoService $service): void
    {
        $validated = $this->validate([
            'documento' => ['required', 'string', 'max:20'],
        ]);

        $documento = $this->normalizeDocumento($validated['documento']);

        if ($documento === '') {
            throw ValidationException::withMessages([
                'documento' => 'Ingresá una cédula válida.',
            ]);
        }

        try {
            $alumno = $service->resolverAlumno($documento);
        } catch (\Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'documento' => 'No se pudo validar la cédula en este momento.',
            ]);
        }

        if (! $alumno) {
            throw ValidationException::withMessages([
                'documento' => 'No encontramos un alumno con esa cédula en la base académica.',
            ]);
        }

        /** @var User $currentUser */
        $currentUser = Auth::user();
        $userToDelete = null;

        $linkedUser = DB::transaction(function () use ($alumno, $currentUser, $documento, &$userToDelete): User {
            $existingStudent = User::query()->where('documento', $documento)->first();

            if ($existingStudent && $existingStudent->isNot($currentUser)) {
                $userToDelete = $currentUser;

                return $this->mergeIntoExistingStudent($existingStudent, $currentUser, $documento, $alumno);
            }

            return $this->updateCurrentUser($currentUser, $documento, $alumno);
        });

        Auth::login($linkedUser, remember: true);
        Session::regenerate();

        if ($userToDelete) {
            $userToDelete->delete();
        }

        $this->redirectAfterLinking();
    }

    protected function redirectAfterLinking(): void
    {
        if (request()->hasSession()) {
            $this->redirectIntended(default: route('alumno.carreras', absolute: false), navigate: true);

            return;
        }

        $this->redirectRoute('alumno.carreras', navigate: true);
    }

    protected function updateCurrentUser(User $user, string $documento, object $alumno): User
    {
        $user->forceFill([
            'documento' => $documento,
            'name' => $this->resolveAlumnoName($alumno, $user->name),
            'email_verified_at' => $user->email_verified_at ?: now(),
        ])->save();

        $this->ensureAlumnoRole($user);

        return $user;
    }

    protected function mergeIntoExistingStudent(User $existingStudent, User $oauthUser, string $documento, object $alumno): User
    {
        if (filled($existingStudent->auth_provider) && (
            $existingStudent->auth_provider !== $oauthUser->auth_provider
            || $existingStudent->auth_provider_id !== $oauthUser->auth_provider_id
        )) {
            throw ValidationException::withMessages([
                'documento' => 'Esa cédula ya está vinculada a otra cuenta.',
            ]);
        }

        $email = $this->resolveMergedEmail($existingStudent, $oauthUser);

        $emailConflict = User::query()
            ->where('email', $email)
            ->whereNotIn('id', [$existingStudent->id, $oauthUser->id])
            ->exists();

        if ($emailConflict) {
            throw ValidationException::withMessages([
                'documento' => 'No se pudo vincular la cuenta porque el correo de Google ya está en uso.',
            ]);
        }

        $oauthProvider = $oauthUser->auth_provider;
        $oauthProviderId = $oauthUser->auth_provider_id;
        $oauthAvatar = $oauthUser->avatar;

        $this->releaseOauthUserUniqueClaimsIfNeeded($oauthUser, $existingStudent, $email);

        $existingStudent->forceFill([
            'name' => $this->resolveAlumnoName($alumno, $existingStudent->name),
            'email' => $email,
            'documento' => $documento,
            'auth_provider' => $oauthProvider,
            'auth_provider_id' => $oauthProviderId,
            'avatar' => $oauthAvatar ?: $existingStudent->avatar,
            'email_verified_at' => $existingStudent->email_verified_at ?: now(),
        ])->save();

        $this->ensureAlumnoRole($existingStudent);

        return $existingStudent;
    }

    protected function releaseOauthUserUniqueClaimsIfNeeded(User $oauthUser, User $existingStudent, string $email): void
    {
        if ($oauthUser->is($existingStudent) || $oauthUser->email !== $email) {
            $shouldReleaseEmail = false;
        } else {
            $shouldReleaseEmail = true;
        }

        $attributes = [
            'auth_provider' => null,
            'auth_provider_id' => null,
        ];

        if ($shouldReleaseEmail) {
            $attributes['email'] = sprintf('oauth-link-%s@pending.invalid', $oauthUser->id);
        }

        $oauthUser->forceFill($attributes)->save();
    }

    protected function ensureAlumnoRole(User $user): void
    {
        Role::findOrCreate('ALUMNO', 'web');

        if (! $user->roles()->exists()) {
            $user->assignRole('ALUMNO');
        }
    }

    protected function resolveAlumnoName(object $alumno, string $fallback): string
    {
        $firstName = trim((string) ($alumno->per_nombre ?? ''));
        $lastName = trim((string) ($alumno->per_apelli ?? ''));
        $fullName = trim($firstName.' '.$lastName);

        if ($fullName !== '') {
            return $fullName;
        }

        return $fallback !== '' ? $fallback : 'Alumno';
    }

    protected function resolveMergedEmail(User $existingStudent, User $oauthUser): string
    {
        if ($this->usesLegacyPlaceholderEmail($existingStudent->email) && filled($oauthUser->email)) {
            return $oauthUser->email;
        }

        return $existingStudent->email;
    }

    protected function usesLegacyPlaceholderEmail(string $email): bool
    {
        return Str::endsWith(Str::lower($email), '@consultor.invalid');
    }

    protected function normalizeDocumento(string $documento): string
    {
        $normalizedDocumento = preg_replace('/\D+/', '', trim($documento));

        if ($normalizedDocumento === null || $normalizedDocumento === '') {
            return trim($documento);
        }

        return $normalizedDocumento;
    }

    protected function defaultRedirect(): string
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user && $user->hasRole('ALUMNO')) {
            return route('alumno.carreras', absolute: false);
        }

        return route('dashboard', absolute: false);
    }
}; ?>

<div class="mx-auto max-w-xl space-y-6">
    <x-mary-header title="Vincular cuenta de alumno" subtitle="Necesitamos tu cédula para enlazar el acceso con Google a tu perfil académico." separator />

    <x-mary-card shadow class="border border-base-300">
        <div class="space-y-4">
            <p class="text-sm text-base-content/70">
                Estás ingresando como <span class="font-semibold text-base-content">{{ auth()->user()->email }}</span>.
                Antes de continuar, vinculá tu cuenta con la cédula que existe en la base académica.
            </p>

            <form wire:submit="linkDocumento" class="space-y-4">
                <div class="form-control w-full">
                    <label class="label" for="documento">
                        <span class="label-text font-medium">Documento (Cédula)</span>
                    </label>

                    <input
                        wire:model="documento"
                        id="documento"
                        type="text"
                        class="input input-bordered w-full"
                        autocomplete="off"
                        placeholder="Ej: 1234567"
                        required
                    />

                    <x-input-error :messages="$errors->get('documento')" class="mt-2" />
                </div>

                <button type="submit" class="btn btn-primary w-full">
                    Vincular cuenta y continuar
                </button>
            </form>

            <p class="text-xs text-base-content/60">
                Si ya usabas el consultor anterior o tu usuario fue sincronizado previamente, esta acción va a enlazar tu acceso con Google a ese mismo registro local.
            </p>
        </div>
    </x-mary-card>
</div>