<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <x-auth-session-status class="mb-4" :status="session('status') !== 'verification-link-sent' ? session('status') : null" />

    <div class="mb-4 text-sm text-base-content/80">
        {{ __('Para proteger tu cuenta necesitamos confirmar que el correo es tuyo. Te enviamos un enlace de verificación: abrilo para completar el ingreso. Si no te llegó, revisá el spam o pedí que te lo reenviemos.') }}
    </div>

    <div class="mb-4 text-sm text-base-content/80">
        {{ __('Verificando tu correo evitamos que otra persona pueda quedarse con tu cuenta.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ __('Te reenviamos el enlace de verificación al correo de tu cuenta.') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <x-primary-button wire:click="sendVerification">
            {{ __('Reenviar correo de verificación') }}
        </x-primary-button>

        <button wire:click="logout" type="submit" class="underline text-sm text-base-content/60 hover:text-base-content rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
            {{ __('Cerrar sesión') }}
        </button>
    </div>
</div>
