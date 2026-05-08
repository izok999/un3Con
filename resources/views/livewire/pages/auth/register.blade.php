<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Role;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $documento = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'documento' => ['required', 'string', 'max:20'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['documento'] = $this->normalizeDocumento($validated['documento']);
        $validated['email'] = Str::lower($validated['email']);

        if ($validated['documento'] === '') {
            $this->addError('documento', 'Ingresá una cédula válida.');

            return;
        }

        $validated['password'] = Hash::make($validated['password']);

        $user = $this->registerOrClaimUser($validated);

        if (! $user) {
            return;
        }

        Role::findOrCreate('ALUMNO', 'web');

        event(new Registered($user));

        if (! $user->hasRole('ALUMNO')) {
            $user->assignRole('ALUMNO');
        }

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }

    /**
     * @param  array{name: string, documento: string, email: string, password: string, password_confirmation: string}  $validated
     */
    protected function registerOrClaimUser(array $validated): ?User
    {
        $existingUser = User::query()->firstWhere('documento', $validated['documento']);

        $emailConflict = User::query()
            ->where('email', $validated['email'])
            ->when($existingUser, fn ($query) => $query->whereKeyNot($existingUser->getKey()))
            ->exists();

        if ($emailConflict) {
            $this->addError('email', 'Ese correo ya está asociado a otra cuenta.');

            return null;
        }

        if (! $existingUser) {
            return User::query()->create($validated);
        }

        if (! $this->canClaimExistingUser($existingUser)) {
            $this->addError('documento', 'Ya existe una cuenta para esa cédula. Iniciá sesión o recuperá tu contraseña.');

            return null;
        }

        $existingUser->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'email_verified_at' => null,
        ])->save();

        return $existingUser;
    }

    protected function canClaimExistingUser(User $user): bool
    {
        return blank($user->auth_provider)
            && $this->usesPlaceholderEmail($user->email);
    }

    protected function usesPlaceholderEmail(string $email): bool
    {
        return Str::endsWith(Str::lower($email), ['@consultor.invalid', '@pending.invalid']);
    }

    protected function normalizeDocumento(string $documento): string
    {
        $normalizedDocumento = preg_replace('/\D+/', '', trim($documento));

        if ($normalizedDocumento === null || $normalizedDocumento === '') {
            return trim($documento);
        }

        return $normalizedDocumento;
    }
}; ?>

<div>
    <h2 class="text-2xl font-bold text-center mb-6 text-base-content">Crear cuenta</h2>

    <form wire:submit="register">
        <!-- Nombre -->
        <div class="form-control w-full">
            <label class="label"><span class="label-text font-medium">Nombre completo</span></label>
            <input wire:model="name" type="text" class="input input-bordered w-full"
                   required autofocus autocomplete="name" placeholder="Juan Pérez" />
            <x-input-error :messages="$errors->get('name')" class="mt-1" />
        </div>

        <!-- Documento -->
        <div class="form-control w-full mt-4">
            <label class="label"><span class="label-text font-medium">Documento (Cédula)</span></label>
            <input wire:model="documento" type="text" class="input input-bordered w-full"
                   required autocomplete="off" placeholder="Ej: 1234567" />
            <x-input-error :messages="$errors->get('documento')" class="mt-1" />
        </div>

        <!-- Email -->
        <div class="form-control w-full mt-4">
            <label class="label"><span class="label-text font-medium">Email</span></label>
            <input wire:model="email" type="email" class="input input-bordered w-full"
                   required autocomplete="username" placeholder="tu@email.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <!-- Password -->
        <div class="form-control w-full mt-4">
            <label class="label"><span class="label-text font-medium">Contraseña</span></label>
            <input wire:model="password" type="password" class="input input-bordered w-full"
                   required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <!-- Confirmar Password -->
        <div class="form-control w-full mt-4">
            <label class="label"><span class="label-text font-medium">Confirmar contraseña</span></label>
            <input wire:model="password_confirmation" type="password" class="input input-bordered w-full"
                   required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
        </div>

        <button type="submit" class="btn btn-primary w-full mt-6">
            Crear cuenta
        </button>
    </form>

    <!-- Separador -->
    <div class="divider text-sm text-base-content/50 my-6">o</div>

    <!-- Registro con Google 
    <a href="{{ route('auth.google.redirect') }}"
       class="btn btn-outline w-full gap-2">
        <svg class="w-5 h-5" viewBox="0 0 24 24">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg>
        Registrarse con Google
    </a> -->

    <p class="text-center text-sm mt-6 text-base-content/60">
        ¿Ya tenés cuenta?
        <a href="{{ route('login') }}" class="text-primary font-medium hover:underline" wire:navigate>Iniciá sesión</a>
    </p>
</div>
