<?php

namespace App\Livewire\Forms;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Form;

class LoginForm extends Form
{
    #[Validate('required|string|max:255')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    #[Validate('boolean')]
    public bool $remember = false;

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        if (! $this->attemptAuthentication()) {
            $this->throwFailedAuthentication();
        }

        $this->clearRateLimit();
    }

    public function attemptAuthentication(): bool
    {
        $this->ensureIsNotRateLimited();

        return Auth::attempt($this->credentials(), $this->remember);
    }

    public function clearRateLimit(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    public function throwFailedAuthentication(): never
    {
        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'form.email' => trans('auth.failed'),
        ]);
    }

    public function usesEmailIdentifier(): bool
    {
        return filter_var($this->loginIdentifier(), FILTER_VALIDATE_EMAIL) !== false;
    }

    public function normalizedDocumento(): string
    {
        return $this->normalizeDocumento($this->loginIdentifier());
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'form.email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->loginIdentifier()).'|'.request()->ip());
    }

    /**
     * @return array{email?: string, documento?: string, password: string}
     */
    protected function credentials(): array
    {
        $identifier = $this->loginIdentifier();

        if ($this->usesEmailIdentifier()) {
            return [
                'email' => $identifier,
                'password' => $this->password,
            ];
        }

        return [
            'documento' => $this->normalizeDocumento($identifier),
            'password' => $this->password,
        ];
    }

    protected function loginIdentifier(): string
    {
        return trim($this->email);
    }

    protected function normalizeDocumento(string $documento): string
    {
        $normalizedDocumento = preg_replace('/\D+/', '', trim($documento));

        if ($normalizedDocumento === null || $normalizedDocumento === '') {
            return trim($documento);
        }

        return $normalizedDocumento;
    }
}
