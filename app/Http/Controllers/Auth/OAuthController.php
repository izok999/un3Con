<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        $socialUser = Socialite::driver('google')->user();
        $email = $socialUser->getEmail();

        abort_unless($email, 422, 'El proveedor OAuth no devolvió un email válido.');

        $user = User::query()
            ->where('email', $email)
            ->orWhere(function ($query) use ($socialUser) {
                $query->where('auth_provider', 'google')
                      ->where('auth_provider_id', $socialUser->getId());
            })
            ->first();

        if (! $user) {
            $user = User::create([
                'name' => $socialUser->getName() ?: $socialUser->getNickname() ?: 'Usuario',
                'email' => $email,
                'password' => null,
                'auth_provider' => 'google',
                'auth_provider_id' => $socialUser->getId(),
                'avatar' => $socialUser->getAvatar(),
                'email_verified_at' => now(),
            ]);

            $user->assignRole('ALUMNO');
        } else {
            $user->update([
                'auth_provider' => 'google',
                'auth_provider_id' => $socialUser->getId(),
                'avatar' => $socialUser->getAvatar(),
                'email_verified_at' => $user->email_verified_at ?: now(),
            ]);
        }

        Auth::login($user, remember: true);

        return redirect()->intended('/dashboard');
    }
}
