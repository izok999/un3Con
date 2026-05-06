<?php

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $identifier = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'identifier' => ['required', 'string', 'max:255'],
        ]);

        $email = $this->resolveResetEmail();

        if (! $email) {
            return;
        }

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(['email' => $email]);

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('identifier', __($status));

            return;
        }

        $this->reset('identifier');

        session()->flash('status', __($status));
    }

    protected function resolveResetEmail(): ?string
    {
        $identifier = trim($this->identifier);

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return Str::lower($identifier);
        }

        $user = User::query()->firstWhere('documento', $this->normalizeDocumento($identifier));

        if (! $user) {
            $this->addError('identifier', __(Password::INVALID_USER));

            return null;
        }

        if (! $this->hasRecoverableEmail($user->email)) {
            $this->addError('identifier', 'Esta cuenta todavía no tiene un correo recuperable. Ingresá con tu documento y PIN o vinculá Google primero.');

            return null;
        }

        return $user->email;
    }

    protected function hasRecoverableEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL)
            && ! Str::endsWith(Str::lower($email), ['@consultor.invalid', '@pending.invalid']);
    }

    protected function normalizeDocumento(string $documento): string
    {
        $normalizedDocumento = preg_replace('/\D+/', '', trim($documento));

        if ($normalizedDocumento === null || $normalizedDocumento === '') {
            return trim($documento);
        }

        return $normalizedDocumento;
    }
}; ?>

<div>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('¿Olvidaste tu contraseña? Ingresá tu correo o tu número de documento. Si la cuenta ya tiene un correo recuperable, te enviaremos el enlace para restablecerla.') }}
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink">
        <!-- Email Address / Documento -->
        <div>
            <x-input-label for="identifier" :value="__('Correo o documento')" />
            <x-text-input wire:model="identifier" id="identifier" class="block mt-1 w-full" type="text" name="identifier" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('identifier')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Email Password Reset Link') }}
            </x-primary-button>
        </div>
    </form>
</div>
