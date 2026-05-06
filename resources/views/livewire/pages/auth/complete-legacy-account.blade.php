<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $name = '';

    public string $documento = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        if (! $this->requiresCompletion($user)) {
            $this->redirectIntended(default: $this->defaultRedirect(), navigate: true);

            return;
        }

        $this->name = $user->name;
        $this->documento = $user->documento ?? '';
        $this->email = '';
    }

    public function completeAccount(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,'.Auth::id()],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $user->forceFill([
            'name' => $validated['name'],
            'email' => Str::lower($validated['email']),
            'password' => Hash::make($validated['password']),
            'email_verified_at' => null,
        ])->save();

        Session::flash('status', 'Cuenta completada. Ahora podés vincular Google desde tu perfil sin volver al login.');

        $this->redirectIntended(default: $this->defaultRedirect(), navigate: true);
    }

    protected function requiresCompletion(User $user): bool
    {
        return filled($user->documento)
            && Str::endsWith(Str::lower($user->email), '@consultor.invalid');
    }

    protected function defaultRedirect(): string
    {
        return route('profile', absolute: false);
    }
}; ?>

<div class="mx-auto max-w-2xl space-y-6">
    <x-mary-header title="Completá tu cuenta" subtitle="Entraste con tu documento y PIN del sistema anterior. Ahora definí tu correo real y tu contraseña local para terminar la activación." separator />

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <x-mary-card shadow class="border border-base-300">
        <form wire:submit="completeAccount" class="space-y-5">
            <div class="grid gap-4 md:grid-cols-2">
                <div class="form-control w-full md:col-span-2">
                    <label class="label" for="name">
                        <span class="label-text font-medium">Nombre completo</span>
                    </label>
                    <input wire:model="name" id="name" type="text" class="input input-bordered w-full" required autocomplete="name" />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div class="form-control w-full">
                    <label class="label" for="documento">
                        <span class="label-text font-medium">Documento (Cédula)</span>
                    </label>
                    <input id="documento" type="text" class="input input-bordered w-full" value="{{ $documento }}" disabled readonly />
                </div>

                <div class="form-control w-full">
                    <label class="label" for="email">
                        <span class="label-text font-medium">Correo real</span>
                    </label>
                    <input wire:model="email" id="email" type="email" class="input input-bordered w-full" required autocomplete="username" placeholder="tu@email.com" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div class="form-control w-full">
                    <label class="label" for="password">
                        <span class="label-text font-medium">Nueva contraseña local</span>
                    </label>
                    <input wire:model="password" id="password" type="password" class="input input-bordered w-full" required autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div class="form-control w-full">
                    <label class="label" for="password_confirmation">
                        <span class="label-text font-medium">Confirmar contraseña</span>
                    </label>
                    <input wire:model="password_confirmation" id="password_confirmation" type="password" class="input input-bordered w-full" required autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                </div>
            </div>

            <div class="rounded-box border border-base-300 bg-base-200/40 p-4 text-sm text-base-content/80">
                Después de este paso vas a poder entrar con tu correo o tu documento y esta nueva contraseña. Al guardar, te llevamos a tu perfil para que puedas vincular Google a la misma cuenta.
            </div>

            <button type="submit" class="btn btn-primary w-full md:w-auto">
                <span wire:loading.remove wire:target="completeAccount">Guardar y continuar</span>
                <span wire:loading wire:target="completeAccount" class="loading loading-spinner loading-sm"></span>
            </button>
        </form>
    </x-mary-card>
</div>
