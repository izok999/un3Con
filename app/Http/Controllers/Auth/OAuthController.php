<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Spatie\Permission\Models\Role;
use Throwable;

class OAuthController extends Controller
{
    public function redirectToGoogle(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(): RedirectResponse
    {
        try {
            $socialUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            report($exception);

            return $this->redirectToLoginWithOAuthError('No se pudo completar el ingreso con Google. Intentá de nuevo.');
        }

        $providerId = (string) $socialUser->getId();
        $email = $socialUser->getEmail();

        if ($providerId === '' || ! $email) {
            return $this->redirectToLoginWithOAuthError('Google no devolvió la información mínima para autenticarte.');
        }

        $user = User::query()
            ->where('auth_provider', 'google')
            ->where('auth_provider_id', $providerId)
            ->first();

        $emailConflict = User::query()
            ->where('email', $email)
            ->when($user, fn ($query) => $query->whereKeyNot($user->getKey()))
            ->exists();

        if ($emailConflict) {
            return $this->redirectToLoginWithOAuthError('Ese correo ya está asociado a otra cuenta. Ingresá con tu método actual para evitar un enlace incorrecto.');
        }

        $attributes = [
            'name' => $this->resolveUserName($socialUser),
            'email' => $email,
            'avatar' => $socialUser->getAvatar(),
            'email_verified_at' => now(),
        ];

        if (! $user) {
            $attributes['password'] = null;
        }

        $user = User::query()->updateOrCreate(
            [
                'auth_provider' => 'google',
                'auth_provider_id' => $providerId,
            ],
            $attributes,
        );

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        Role::findOrCreate('ALUMNO', 'web');

        if (! $user->roles()->exists()) {
            $user->assignRole('ALUMNO');
        }

        if (blank($user->documento)) {
            return redirect()->route('auth.oauth.link-documento');
        }

        return redirect()->intended($this->defaultRedirectFor($user));
    }

    protected function defaultRedirectFor(User $user): string
    {
        if ($user->hasRole('ALUMNO')) {
            return route('alumno.carreras');
        }

        return route('dashboard');
    }

    protected function redirectToLoginWithOAuthError(string $message): RedirectResponse
    {
        return redirect()
            ->route('login')
            ->withErrors(['oauth' => $message]);
    }

    protected function resolveUserName(SocialiteUser $socialUser): string
    {
        return $socialUser->getName()
            ?: $socialUser->getNickname()
            ?: 'Alumno';
    }
}
