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
    protected const GOOGLE_LINK_EXISTING_INTENT = 'link-existing';

    public function redirectToGoogle(): RedirectResponse
    {
        session()->forget('auth.google.intent');

        return Socialite::driver('google')->redirect();
    }

    public function redirectExistingAccountToGoogle(): RedirectResponse
    {
        session(['auth.google.intent' => self::GOOGLE_LINK_EXISTING_INTENT]);

        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(): RedirectResponse
    {
        $intent = (string) session()->pull('auth.google.intent', '');

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

        $existingUserWithEmail = User::query()
            ->where('email', $email)
            ->when($user, fn ($query) => $query->whereKeyNot($user->getKey()))
            ->first();

        if ($existingUserWithEmail) {
            $user = $this->resolveEmailConflict(
                existingUserWithEmail: $existingUserWithEmail,
                linkedUser: $user,
                providerId: $providerId,
                intent: $intent,
            );

            if (! $user) {
                return $this->redirectToLoginWithOAuthError('Ese correo ya está asociado a otra cuenta. Usá el botón "Vincular cuenta existente con Google" para enlazarla de forma segura.');
            }
        }

        $attributes = [
            'name' => $this->resolveUserName($socialUser),
            'email' => $email,
            'avatar' => $socialUser->getAvatar(),
            'email_verified_at' => now(),
            'auth_provider' => 'google',
            'auth_provider_id' => $providerId,
        ];

        if ($user) {
            $user->forceFill($attributes)->save();
        } else {
            $attributes['password'] = null;

            $user = User::query()->create($attributes);
        }

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

    protected function resolveEmailConflict(
        User $existingUserWithEmail,
        ?User $linkedUser,
        string $providerId,
        string $intent,
    ): ?User {
        if ($linkedUser) {
            return $linkedUser->is($existingUserWithEmail) ? $linkedUser : null;
        }

        if ($intent !== self::GOOGLE_LINK_EXISTING_INTENT) {
            return null;
        }

        if (filled($existingUserWithEmail->auth_provider) && $existingUserWithEmail->auth_provider !== 'google') {
            return null;
        }

        if (
            filled($existingUserWithEmail->auth_provider_id)
            && $existingUserWithEmail->auth_provider_id !== $providerId
        ) {
            return null;
        }

        return $existingUserWithEmail;
    }

    protected function resolveUserName(SocialiteUser $socialUser): string
    {
        return $socialUser->getName()
            ?: $socialUser->getNickname()
            ?: 'Alumno';
    }
}
