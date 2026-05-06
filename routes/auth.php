<?php

use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('guest')->group(function () {
    Volt::route('register', 'pages.auth.register')
        ->name('register');

    Volt::route('login', 'pages.auth.login')
        ->name('login');

    Volt::route('forgot-password', 'pages.auth.forgot-password')
        ->name('password.request');

    Volt::route('reset-password/{token}', 'pages.auth.reset-password')
        ->name('password.reset');

    // OAuth — Google
    Route::get('auth/google/redirect', [OAuthController::class, 'redirectToGoogle'])
        ->name('auth.google.redirect');
});

Route::get('auth/google/link-existing', [OAuthController::class, 'redirectExistingAccountToGoogle'])
    ->name('auth.google.link-existing');

Route::get('auth/google/callback', [OAuthController::class, 'handleGoogleCallback'])
    ->name('auth.google.callback');

Route::middleware('auth')->group(function () {
    Volt::route('auth/complete-account', 'pages.auth.complete-legacy-account')
        ->name('auth.legacy.complete-account');

    Volt::route('auth/link-documento', 'pages.auth.link-documento')
        ->name('auth.oauth.link-documento');

    Volt::route('verify-email', 'pages.auth.verify-email')
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Volt::route('confirm-password', 'pages.auth.confirm-password')
        ->name('password.confirm');

    Route::post('logout', function () {
        (new Logout)();

        return redirect('/');
    })->name('logout');
});
