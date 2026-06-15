<?php

use App\Livewire\Forms\LegacyAlumnoLoginForm;
use App\Livewire\Forms\LoginForm;
use App\Models\User;
use App\Services\AlumnoExternoService;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public LegacyAlumnoLoginForm $legacyForm;

    public function login(AlumnoExternoService $service): void
    {
        $this->form->validate();

        if ($this->form->attemptAuthentication()) {
            $this->form->clearRateLimit();
            Session::regenerate();
            $this->redirectRoute('dashboard', navigate: true);

            return;
        }

        if ($this->form->usesEmailIdentifier()) {
            $this->form->throwFailedAuthentication();
        }

        try {
            $user = $this->attemptLegacyLogin($service);
        } catch (ValidationException $exception) {
            throw $this->mapLegacyValidationException($exception);
        }

        Session::regenerate();

        if ($this->requiresLegacyAccountCompletion($user->email)) {
            $this->redirectRoute('auth.legacy.complete-account', navigate: true);

            return;
        }

        $this->redirectRoute('dashboard', navigate: true);
    }

    protected function requiresLegacyAccountCompletion(string $email): bool
    {
        return Str::endsWith(Str::lower($email), '@consultor.invalid');
    }

    protected function attemptLegacyLogin(AlumnoExternoService $service): User
    {
        $this->legacyForm->documento = $this->form->normalizedDocumento();
        $this->legacyForm->pin = $this->form->password;
        $this->legacyForm->remember = $this->form->remember;

        return $this->legacyForm->authenticate($service);
    }

    protected function mapLegacyValidationException(ValidationException $exception): ValidationException
    {
        $messages = $exception->errors();
        $mappedMessages = [];

        if (array_key_exists('legacyForm.documento', $messages)) {
            $mappedMessages['form.email'] = $messages['legacyForm.documento'];
        }

        if (array_key_exists('legacyForm.pin', $messages)) {
            $mappedMessages['form.password'] = $messages['legacyForm.pin'];
        }

        if ($mappedMessages === []) {
            return $exception;
        }

        return ValidationException::withMessages($mappedMessages);
    }
}; ?>

<div class="antialiased">
    <h2 class="text-xl font-bold text-center mb-4 text-base-content">{{ __('Iniciar sesión') }}</h2>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    @if ($errors->has('oauth'))
        <x-mary-alert title="{{ $errors->first('oauth') }}" icon="o-exclamation-triangle" class="alert-error mb-4" />
    @endif

    <form wire:submit="login">
        <!-- Email / Documento -->
        <div class="form-control w-full">
            <label class="label" for="email"><span class="label-text text-base font-semibold">{{ __('Correo o documento') }}</span></label>
            <div class="input input-bordered flex items-center gap-2 w-full">
                <svg class="h-3.5 w-3.5 shrink-0 opacity-40" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                </svg>
                <input wire:model="form.email" id="email" type="text"
                       class="grow text-lg"
                       required autofocus autocomplete="username" placeholder="{{ __('tu@email.com o 1234567') }}" />
            </div>
            <x-input-error :messages="$errors->get('form.email')" class="mt-1" />
        </div>

        <!-- Password -->
        <div class="form-control w-full mt-3">
            <label class="label" for="password"><span class="label-text text-base font-semibold">{{ __('Contraseña o PIN') }}</span></label>
            <div class="input input-bordered flex items-center gap-2 w-full">
                <svg class="h-3.5 w-3.5 shrink-0 opacity-40" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                </svg>
                <input wire:model="form.password" id="password" type="password"
                       class="grow text-lg"
                       required autocomplete="current-password" />
            </div>
            <x-input-error :messages="$errors->get('form.password')" class="mt-1" />
        </div>

        <div class="flex gap-2 rounded-[0.75rem] border border-primary/20 bg-primary/5 p-3 mt-3 text-base font-medium text-base-content/70">
            <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary/60" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
            </svg>
            <p>{{ __('Podés entrar con tu correo y contraseña, o con tu documento usando tu contraseña local o el PIN del consultor anterior.') }}</p>
        </div>

        <!-- Remember + Forgot -->
        <div class="flex items-center justify-between mt-3">
            <label class="label cursor-pointer gap-1.5">
                <input wire:model="form.remember" type="checkbox" class="checkbox checkbox-primary checkbox-xs" />
                <span class="text-base font-semibold">{{ __('Recordarme') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-base font-semibold text-primary hover:underline" href="{{ route('password.request') }}" wire:navigate>
                    {{ __('¿Olvidaste tu contraseña?') }}
                </a>
            @endif
        </div>

        <button type="submit" class="btn btn-primary w-full mt-4">
            <span wire:loading.remove wire:target="login">{{ __('Acceder') }}</span>
            <span wire:loading wire:target="login" class="loading loading-spinner loading-sm"></span>
        </button>
    </form>

    <div class="divider text-base text-base-content/40 my-3">{{ __('o') }}</div>

    <!-- Login con Google -->
    <a href="{{ route('auth.google.redirect') }}"
       class="btn btn-outline w-full gap-2">
        <svg class="w-5 h-5" viewBox="0 0 24 24">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg>
        {{ __('Ingresar con Google') }}
    </a>

    <p class="text-base font-medium text-base-content/50 mt-2 text-center leading-relaxed">
        {{ __('Si tu cuenta local ya usa el mismo correo que Google, la vamos a enlazar automáticamente. Si es tu primer ingreso con Google, después te vamos a pedir tu cédula para enlazar tu cuenta de alumno.') }}
    </p>

    <p class="text-center text-base font-semibold mt-4 text-base-content/60">
        {{ __('¿No tenés cuenta?') }}
        <a href="{{ route('register') }}" class="text-primary hover:underline" wire:navigate>{{ __('Registrate') }}</a>
    </p>
</div>
