<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Bcrypt ignora todo lo que pase de 72 bytes, así que cortamos antes con un
     * límite que el alumno pueda entender.
     */
    protected const MIN_LENGTH = 8;

    protected const MAX_LENGTH = 64;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * La contraseña actual solo se puede verificar contra el hash, así que la
     * revisamos al salir del campo y no en cada tecla.
     */
    public function updatedCurrentPassword(): void
    {
        $this->resetValidation('password');

        $this->validateOnly('current_password', $this->passwordRules(), $this->passwordMessages());
    }

    /**
     * Mientras escribe manda la lista de requisitos; los errores del servidor
     * quedarían viejos y contradiciéndola.
     */
    public function updatedPassword(): void
    {
        $this->resetValidation(['password', 'password_confirmation']);
    }

    public function updatedPasswordConfirmation(): void
    {
        $this->resetValidation(['password', 'password_confirmation']);
    }

    /**
     * Estado en vivo de cada requisito: 'ok', 'fail' o 'pending' cuando todavía
     * no hay nada escrito como para juzgarlo.
     */
    #[Computed]
    public function passwordChecks(): array
    {
        $length = mb_strlen($this->password);

        return [
            [
                'label' => __('Al menos :min caracteres.', ['min' => self::MIN_LENGTH]),
                'state' => match (true) {
                    $length === 0 => 'pending',
                    $length >= self::MIN_LENGTH => 'ok',
                    default => 'fail',
                },
            ],
            [
                'label' => __('Como máximo :max caracteres.', ['max' => self::MAX_LENGTH]),
                'state' => match (true) {
                    $length === 0 => 'pending',
                    $length <= self::MAX_LENGTH => 'ok',
                    default => 'fail',
                },
            ],
            [
                'label' => __('Distinta a tu contraseña actual.'),
                'state' => match (true) {
                    $length === 0 || $this->current_password === '' => 'pending',
                    $this->password !== $this->current_password => 'ok',
                    default => 'fail',
                },
            ],
            [
                'label' => __('Las dos contraseñas coinciden.'),
                'state' => match (true) {
                    $length === 0 || $this->password_confirmation === '' => 'pending',
                    $this->password === $this->password_confirmation => 'ok',
                    default => 'fail',
                },
            ],
        ];
    }

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate($this->passwordRules(), $this->passwordMessages());
        } catch (ValidationException $e) {
            // Solo borramos la contraseña actual: si vaciáramos las nuevas, el
            // alumno perdería lo que escribió junto con el mensaje que le dice
            // qué corregir.
            $this->reset('current_password');

            throw $e;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }

    protected function passwordRules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => [
                'required',
                'string',
                'different:current_password',
                'max:'.self::MAX_LENGTH,
                Password::defaults()->min(self::MIN_LENGTH),
                'confirmed',
            ],
        ];
    }

    protected function passwordMessages(): array
    {
        return [
            'current_password.required' => __('Ingresá tu contraseña actual.'),
            'current_password.current_password' => __('La contraseña actual no es correcta.'),
            'password.required' => __('Ingresá una contraseña nueva.'),
            'password.min' => __('La contraseña es muy corta: necesita al menos :min caracteres.'),
            'password.max' => __('La contraseña es muy larga: no puede pasar de :max caracteres.'),
            'password.different' => __('La contraseña nueva tiene que ser distinta a la actual.'),
            'password.confirmed' => __('Las contraseñas no coinciden. Volvé a escribirlas.'),
        ];
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-base-content">
            {{ __('Actualizar Contraseña') }}
        </h2>

        <p class="mt-1 text-sm text-base-content/70">
            {{ __('Asegúrese de que su cuenta esté utilizando una contraseña larga y aleatoria para mantenerse seguro.') }}
        </p>
    </header>

    <form wire:submit="updatePassword" class="mt-6 space-y-6">
        <div>
            <x-input-label for="update_password_current_password" :value="__('Contraseña Actual')" class="text-base-content" />
            <x-text-input wire:model.blur="current_password" id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full px-3 bg-base-50 text-base-content placeholder:text-base-content/70 focus:border-primary focus:ring-primary rounded-md shadow-sm" autocomplete="current-password" />
            <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password" :value="__('Nueva Contraseña')" class="text-base-content" />
            <x-text-input wire:model.live.debounce.300ms="password" id="update_password_password" name="password" type="password" class="mt-1 block w-full px-3 bg-base-100 text-base-content placeholder:text-base-content/70 focus:border-primary focus:ring-primary rounded-md shadow-sm" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />

            <ul class="mt-3 space-y-1 text-sm" aria-live="polite">
                @foreach ($this->passwordChecks as $check)
                    @php
                        [$tone, $icon] = match ($check['state']) {
                            'ok' => ['text-success', 'o-check-circle'],
                            'fail' => ['text-error', 'o-x-circle'],
                            default => ['text-base-content/60', 'o-minus-circle'],
                        };
                    @endphp

                    <li class="flex items-center gap-2 {{ $tone }}">
                        <x-icon :name="$icon" class="h-4 w-4 shrink-0" />
                        <span>{{ $check['label'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="__('Confirmar Contraseña')" class="text-base-content" />
            <x-text-input wire:model.live.debounce.300ms="password_confirmation" id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full px-3 bg-base-100 text-base-content placeholder:text-base-content/70 focus:border-primary focus:ring-primary rounded-md shadow-sm" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />

            @if ($password_confirmation !== '' && $password !== $password_confirmation)
                <p class="mt-2 text-sm text-error" aria-live="polite">
                    {{ __('Las contraseñas no coinciden. Volvé a escribirlas.') }}
                </p>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Guardar') }}</x-primary-button>

            <x-action-message class="me-3" on="password-updated">
                {{ __('Contraseña actualizada.') }}
            </x-action-message>
        </div>
    </form>
</section>
