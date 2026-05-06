<?php

use App\Livewire\Forms\LegacyAlumnoLoginForm;
use App\Livewire\Forms\LoginForm;
use App\Services\AlumnoExternoService;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public LegacyAlumnoLoginForm $legacyForm;

    public function login(): void
    {
        $this->form->validate();
        $this->form->authenticate();
        Session::regenerate();
        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    public function loginAlumno(AlumnoExternoService $service): void
    {
        $this->legacyForm->validate();
        $user = $this->legacyForm->authenticate($service);
        Session::regenerate();

        if ($this->requiresLegacyAccountCompletion($user->email)) {
            $this->redirectRoute('auth.legacy.complete-account', navigate: true);

            return;
        }

        $this->redirectIntended(default: route('alumno.carreras', absolute: false), navigate: true);
    }

    protected function requiresLegacyAccountCompletion(string $email): bool
    {
        return Str::endsWith(Str::lower($email), '@consultor.invalid');
    }
}; ?>

<div>
    <h2 class="text-2xl font-bold text-center mb-6 text-base-content">Iniciar sesión</h2>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    @if ($errors->has('oauth'))
        <x-mary-alert title="{{ $errors->first('oauth') }}" icon="o-exclamation-triangle" class="alert-error mb-4" />
    @endif

    <form wire:submit="login">
        <!-- Email / Documento -->
        <div class="form-control w-full">
            <label class="label"><span class="label-text font-medium">Correo o documento</span></label>
            <input wire:model="form.email" id="email" type="text"
                   class="input input-bordered w-full"
                   required autofocus autocomplete="username" placeholder="tu@email.com o 1234567" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-1" />
        </div>

        <!-- Password -->
        <div class="form-control w-full mt-4">
            <label class="label"><span class="label-text font-medium">Contraseña</span></label>
            <input wire:model="form.password" id="password" type="password"
                   class="input input-bordered w-full"
                   required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('form.password')" class="mt-1" />
        </div>

        <!-- Remember + Forgot -->
        <div class="flex items-center justify-between mt-4">
            <label class="label cursor-pointer gap-2">
                <input wire:model="form.remember" type="checkbox" class="checkbox checkbox-primary checkbox-sm" />
                <span class="label-text text-sm">Recordarme</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm text-primary hover:underline" href="{{ route('password.request') }}" wire:navigate>
                    ¿Olvidaste tu contraseña?
                </a>
            @endif
        </div>

        <button type="submit" class="btn btn-primary w-full mt-6">
            Iniciar sesión
        </button>
    </form>

    <div class="divider text-sm text-base-content/50 my-6">o</div>

    <form wire:submit="loginAlumno">
        <div class="rounded-box border border-base-300 bg-base-200/40 p-4">
            <h3 class="font-semibold text-base-content">Acceso para alumnos del consultor anterior</h3>
            <p class="text-sm text-base-content/70 mt-1">
                Ingresá con tu documento y el PIN que ya usabas en el consultor académico anterior.
            </p>

            <div class="form-control w-full mt-4">
                <label class="label"><span class="label-text font-medium">Documento (Cédula)</span></label>
                <input wire:model="legacyForm.documento" type="text"
                       class="input input-bordered w-full"
                       required autocomplete="username" placeholder="Ej: 1234567" />
                <x-input-error :messages="$errors->get('legacyForm.documento')" class="mt-1" />
            </div>

            <div class="form-control w-full mt-4">
                <label class="label"><span class="label-text font-medium">PIN</span></label>
                <input wire:model="legacyForm.pin" type="password"
                       class="input input-bordered w-full"
                       required autocomplete="current-password" />
                <x-input-error :messages="$errors->get('legacyForm.pin')" class="mt-1" />
            </div>

            <label class="label cursor-pointer gap-2 mt-2 justify-start">
                <input wire:model="legacyForm.remember" type="checkbox" class="checkbox checkbox-primary checkbox-sm" />
                <span class="label-text text-sm">Recordarme en este dispositivo</span>
            </label>

            <button type="submit" class="btn btn-outline w-full mt-4">
                Ingresar con cédula y PIN
            </button>
        </div>
    </form>

    <div class="divider text-sm text-base-content/50 my-6">o</div>

    <!-- Login con Google -->
    <a href="{{ route('auth.google.redirect') }}"
       class="btn btn-outline w-full gap-2">
        <svg class="w-5 h-5" viewBox="0 0 24 24">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg>
        Ingresar con Google
    </a>

    <a href="{{ route('auth.google.link-existing') }}"
       class="btn btn-ghost w-full gap-2 mt-3 border border-base-300">
        <svg class="w-5 h-5" viewBox="0 0 24 24">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg>
        Vincular cuenta existente con Google
    </a>

    <p class="text-sm text-base-content/70 mt-3 text-center">
        Si ya tenés una cuenta local con email y contraseña, usá el botón de vinculación. Si es tu primer ingreso con Google, después te vamos a pedir tu cédula para enlazar tu cuenta de alumno.
    </p>

    <p class="text-center text-sm mt-6 text-base-content/60">
        ¿No tenés cuenta?
        <a href="{{ route('register') }}" class="text-primary font-medium hover:underline" wire:navigate>Registrate</a>
    </p>
</div>
