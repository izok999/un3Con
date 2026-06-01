<?php

use App\Enums\RoleName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Raíz — redirige al dashboard si autenticado, si no al login
Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// Cambio de idioma — accesible para usuarios autenticados y guests
Route::post('/locale', function (Request $request) {
    $supported = ['es', 'en', 'pt', 'gn'];
    $locale = $request->input('locale');

    if (in_array($locale, $supported, strict: true)) {
        session(['locale' => $locale]);

        if ($request->user()) {
            $request->user()->update(['locale' => $locale]);
        }
    }

    return back();
})->name('locale.switch');

// Bienvenida — solo ADMIN o FUNCIONARIO
Route::get('/bienvenida', fn () => view('welcome'))
    ->middleware(['auth', 'role:'.RoleName::middleware(
        RoleName::Admin,
        RoleName::AdminUnidadAcademica,
        RoleName::Funcionario,
    )])
    ->name('welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'legacy.account.complete', 'verified', 'oauth.documento'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth', 'legacy.account.complete', 'oauth.documento'])
    ->name('profile');

// Portal del alumno — solo rol ALUMNO
Route::middleware(['auth', 'legacy.account.complete', 'oauth.documento', 'role:'.RoleName::Alumno->value])->group(function () {
    Volt::route('/mis-carreras', 'alumno.mis-carreras')->name('alumno.carreras');
    Volt::route('/mis-carreras/{halId}', 'alumno.detalle-carrera')->name('alumno.carreras.show');
    Volt::route('/extracto-academico', 'alumno.extracto-academico')->name('alumno.extracto');
    Volt::route('/mis-materias', 'alumno.mis-materias')->name('alumno.materias');
    Volt::route('/evaluacion-docente', 'alumno.evaluacion-docente.index')->name('alumno.evaluacion-docente');
    Volt::route('/evaluacion-docente/{docente}', 'alumno.evaluacion-docente.form')->name('alumno.evaluacion-docente.form');
    Volt::route('/mis-deudas', 'alumno.mis-deudas')->name('alumno.deudas');
});

// Panel de administración — ADMIN general o por unidad académica
Route::middleware([
    'auth',
    'role:'.RoleName::middleware(
        RoleName::Admin,
        RoleName::AdminUnidadAcademica,
    ),
    'academic.unit.scope',
])->group(function () {
    Route::get('/admin', fn () => view('dashboard'))->name('admin.dashboard');
    Volt::route('/admin/consulta-alumno', 'admin.consulta-alumno')->name('admin.consulta-alumno');
    Volt::route('/admin/evaluacion-docente/docentes', 'admin.evaluacion-docente.docentes')->name('admin.evaluacion-docente.docentes');
});

// Panel de administración global — solo ADMIN general
Route::middleware(['auth', 'role:'.RoleName::Admin->value])->group(function () {
    Volt::route('/admin/administradores-unidades', 'admin.administradores-unidades')->name('admin.academic-unit-admins');
    Volt::route('/admin/evaluacion-docente/configuracion', 'admin.evaluacion-docente.configuracion')->name('admin.evaluacion-docente.configuracion');
});

require __DIR__.'/auth.php';
