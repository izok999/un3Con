<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Raíz — redirige al dashboard si autenticado, si no al login
Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// Bienvenida — solo ADMIN o FUNCIONARIO
Route::get('/bienvenida', fn () => view('welcome'))
    ->middleware(['auth', 'role:ADMIN|FUNCIONARIO'])
    ->name('welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'legacy.account.complete', 'verified', 'oauth.documento'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth', 'legacy.account.complete', 'oauth.documento'])
    ->name('profile');

// Portal del alumno — solo rol ALUMNO
Route::middleware(['auth', 'legacy.account.complete', 'oauth.documento', 'role:ALUMNO'])->group(function () {
    Volt::route('/mis-carreras', 'alumno.mis-carreras')->name('alumno.carreras');
    Volt::route('/mis-carreras/{halId}', 'alumno.detalle-carrera')->name('alumno.carreras.show');
    Volt::route('/extracto-academico', 'alumno.extracto-academico')->name('alumno.extracto');
    Volt::route('/mis-materias', 'alumno.mis-materias')->name('alumno.materias');
    Volt::route('/mis-deudas', 'alumno.mis-deudas')->name('alumno.deudas');
});

// Panel de administración — solo rol ADMIN
Route::middleware(['auth', 'role:ADMIN'])->group(function () {
    Route::get('/admin', fn () => view('dashboard'))->name('admin.dashboard');
    Volt::route('/admin/consulta-alumno', 'admin.consulta-alumno')->name('admin.consulta-alumno');
});

require __DIR__.'/auth.php';
