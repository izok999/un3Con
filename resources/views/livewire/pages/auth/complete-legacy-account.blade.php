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
        $validated = $this->validate(
            rules: [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,'.Auth::id()],
                'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            ],
            messages: [
                'name.required' => __('Ingresá tu nombre completo.'),
                'name.max' => __('El nombre no puede superar los :max caracteres.'),
                'email.required' => __('El correo es obligatorio: lo necesitamos para verificar que la cuenta es tuya.'),
                'email.email' => __('Ingresá un correo válido, por ejemplo nombre@dominio.com.'),
                'email.lowercase' => __('Escribí el correo todo en minúsculas.'),
                'email.max' => __('El correo no puede superar los :max caracteres.'),
                'email.unique' => __('Ese correo ya está en uso por otra cuenta. Si es tuyo, ingresá con ese correo o recuperá su contraseña desde el login.'),
                'password.required' => __('Ingresá una contraseña nueva.'),
                'password.min' => __('La contraseña es muy corta: necesita al menos :min caracteres.'),
                'password.confirmed' => __('Las contraseñas no coinciden. Volvé a escribirlas.'),
            ],
        );

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

        // Desde este momento el PIN del consultor queda deshabilitado (ver
        // LegacyAlumnoLoginForm): la cuenta solo se termina de activar cuando
        // el alumno demuestra que el correo declarado es suyo.
        $user->sendEmailVerificationNotification();

        Session::flash('status', __('¡Casi listo! Te enviamos un enlace de verificación a tu correo: abrilo para completar el ingreso. Desde ahora entrás solo con tu nueva contraseña; el PIN del consultor anterior quedó deshabilitado.'));

        $this->redirectRoute('verification.notice', navigate: true);
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
    <x-mary-header title="Completá tu cuenta" subtitle="Entraste con tu documento y PIN del sistema anterior. Ahora definí tu correo real y tu contraseña local para terminar la activación." icon="o-user-circle" separator />

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <x-mary-card shadow class="border border-base-300">
        <form wire:submit="completeAccount" class="space-y-5">
            <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <x-mary-input
                        wire:model="name"
                        id="name"
                        label="Nombre completo"
                        icon="o-user"
                        required
                        autocomplete="name"
                        first-error-only
                    />
                </div>

                <x-mary-input
                    id="documento"
                    label="Documento (Cédula)"
                    icon="o-identification"
                    value="{{ $documento }}"
                    disabled
                    readonly
                    omit-error
                />

                <x-mary-input
                    wire:model="email"
                    id="email"
                    type="email"
                    label="Correo real"
                    icon="o-envelope"
                    placeholder="tu@email.com"
                    hint="A este correo te va a llegar el enlace de verificación."
                    required
                    autocomplete="username"
                    first-error-only
                />

                <x-mary-password
                    wire:model="password"
                    id="password"
                    label="Nueva contraseña local"
                    hint="Mínimo 8 caracteres."
                    right
                    required
                    autocomplete="new-password"
                    first-error-only
                />

                <x-mary-password
                    wire:model="password_confirmation"
                    id="password_confirmation"
                    label="Confirmar contraseña"
                    right
                    required
                    autocomplete="new-password"
                    first-error-only
                />
            </div>

            <div class="rounded-box border border-base-300 bg-base-200/40 p-4 text-sm text-base-content/80">
                Después de este paso vas a entrar con tu correo o tu documento y esta nueva contraseña: el PIN del consultor anterior queda deshabilitado. Te vamos a enviar un enlace a tu correo para verificar que es tuyo y así proteger tu cuenta.
            </div>

            <button type="submit" class="btn btn-primary w-full md:w-auto">
                <span wire:loading.remove wire:target="completeAccount">Guardar y continuar</span>
                <span wire:loading wire:target="completeAccount" class="loading loading-spinner loading-sm"></span>
            </button>
        </form>
    </x-mary-card>
</div>
