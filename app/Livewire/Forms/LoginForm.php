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
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->credentials(), $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'form.email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
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

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
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
