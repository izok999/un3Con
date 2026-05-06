<?php

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Mount the component.
     */
    public function mount(string $token): void
    {
        $this->token = $token;

        $this->email = request()->string('email');
    }

    /**
     * Reset the password for the given user.
     */
    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $email = $this->resolveResetEmail();

        if (! $email) {
            return;
        }

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            [
                'email' => $email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        if ($status != Password::PASSWORD_RESET) {
            $this->addError('email', __($status));

            return;
        }

        Session::flash('status', __($status));

        $this->redirectRoute('login', navigate: true);
    }

    protected function resolveResetEmail(): ?string
    {
        $identifier = trim($this->email);

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return Str::lower($identifier);
        }

        $user = User::query()->firstWhere('documento', $this->normalizeDocumento($identifier));

        if (! $user) {
            $this->addError('email', __(Password::INVALID_USER));

            return null;
        }

        if (! $this->hasRecoverableEmail($user->email)) {
            $this->addError('email', 'Esta cuenta todavía no tiene un correo recuperable. Ingresá con tu documento y PIN o vinculá Google primero.');

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
    <form wire:submit="resetPassword">
        <!-- Email Address / Documento -->
        <div>
            <x-input-label for="email" :value="__('Correo o documento')" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="text" name="email" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input wire:model="password" id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full"
                          type="password"
                          name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Reset Password') }}
            </x-primary-button>
        </div>
    </form>
</div>
