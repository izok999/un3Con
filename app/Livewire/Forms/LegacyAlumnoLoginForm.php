<?php

namespace App\Livewire\Forms;

use App\Models\User;
use App\Services\AlumnoExternoService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Form;
use Spatie\Permission\Models\Role;
use stdClass;
use Throwable;

class LegacyAlumnoLoginForm extends Form
{
    #[Validate('required|string|max:20')]
    public string $documento = '';

    #[Validate('required|string|max:50')]
    public string $pin = '';

    #[Validate('boolean')]
    public bool $remember = false;

    /**
     * Attempt to authenticate the alumno against the legacy consultor.
     *
     * @throws ValidationException
     */
    public function authenticate(AlumnoExternoService $service): User
    {
        $this->ensureIsNotRateLimited();

        $documento = $this->normalizedDocumento();

        try {
            $legacyUser = $service->autenticarConsultor($documento, trim($this->pin), request()->ip());
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'legacyForm.documento' => 'El acceso con cédula y PIN no está disponible en este momento.',
            ]);
        }

        if (! $legacyUser) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'legacyForm.documento' => 'Las credenciales del consultor anterior no son válidas.',
            ]);
        }

        $alumno = $service->resolverAlumno($documento);

        if (! $alumno) {
            throw ValidationException::withMessages([
                'legacyForm.documento' => 'Se validó el acceso, pero no se encontró el alumno asociado a ese documento.',
            ]);
        }

        $user = User::query()->firstOrCreate(
            ['documento' => $documento],
            [
                'name' => $this->resolveAlumnoName($alumno),
                'email' => sprintf('alumno-%s@consultor.invalid', $documento),
                'email_verified_at' => now(),
                'password' => Str::random(40),
            ],
        );

        Role::findOrCreate('ALUMNO', 'web');

        if (! $user->hasRole('ALUMNO')) {
            $user->assignRole('ALUMNO');
        }

        Auth::login($user, $this->remember);

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    /**
     * Ensure the legacy authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'legacyForm.documento' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the legacy authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->normalizedDocumento()).'|'.request()->ip());
    }

    protected function normalizedDocumento(): string
    {
        $documento = preg_replace('/\D+/', '', trim($this->documento));

        return $documento !== '' ? $documento : trim($this->documento);
    }

    protected function resolveAlumnoName(stdClass $alumno): string
    {
        $firstName = trim((string) ($alumno->per_nombre ?? ''));
        $lastName = trim((string) ($alumno->per_apelli ?? ''));
        $fullName = trim($firstName.' '.$lastName);

        return $fullName !== '' ? $fullName : 'Alumno';
    }
}
