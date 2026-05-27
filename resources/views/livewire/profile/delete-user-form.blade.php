<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-base-content">
            {{ __('Borrar Cuenta') }}
        </h2>

        <p class="mt-1 text-sm text-base-content/70">
            {{ __('Una vez que su cuenta sea eliminada, todos sus recursos y datos serán eliminados permanentemente. Antes de eliminar su cuenta, por favor descargue cualquier dato o información que desee conservar.') }}
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('Borrar Cuenta') }}</x-danger-button>

    <x-dialog-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="z-50 bg-base-100 text-base-content">
        <form wire:submit="deleteUser" class="p-6 space-y-6 bg-base-100 text-base-content">

            <h2 class="text-lg font-medium text-base-content">
                {{ __('¿Está seguro de que desea eliminar su cuenta?') }}
            </h2>

            <p class="mt-1 text-sm text-base-content/70">
                {{ __('Una vez que su cuenta sea eliminada, todos sus recursos y datos serán eliminados permanentemente. Por favor, ingrese su contraseña para confirmar que desea eliminar permanentemente su cuenta.') }}
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="{{ __('Contraseña') }}" class="sr-only" />

                <x-text-input
                    wire:model="password"
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 px-3 bg-base-100 text-base-content placeholder:text-base-content/70 focus:border-primary focus:ring-primary rounded-md shadow-sm"
                    placeholder="{{ __('Contraseña') }}"
                />

                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Cancelar') }}
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    {{ __('Borrar Cuenta') }}
                </x-danger-button>
            </div>
        </form>
    </x-dialog-modal>
</section>
