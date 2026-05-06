<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-auth-session-status class="sm:px-2" :status="session('status')" />

            @if (filled(auth()->user()?->auth_provider))
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg border border-emerald-200 bg-emerald-50/40">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-start gap-4">
                            @if (filled(auth()->user()?->avatar))
                                <img
                                    src="{{ auth()->user()->avatar }}"
                                    alt="Avatar de Google"
                                    class="h-12 w-12 rounded-full border border-emerald-200 object-cover"
                                />
                            @else
                                <div class="flex h-12 w-12 items-center justify-center rounded-full border border-emerald-200 bg-white text-emerald-600">
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" aria-hidden="true">
                                        <path fill="currentColor" d="M21.35 11.1h-9.18v2.98h5.27c-.23 1.48-1.76 4.36-5.27 4.36-3.17 0-5.75-2.62-5.75-5.85s2.58-5.85 5.75-5.85c1.8 0 3 .77 3.69 1.44l2.52-2.43C16.76 4.18 14.67 3.2 12.17 3.2 7.18 3.2 3.13 7.27 3.13 12.3s4.05 9.1 9.04 9.1c5.22 0 8.68-3.67 8.68-8.84 0-.59-.06-1.03-.14-1.46Z"/>
                                    </svg>
                                </div>
                            @endif

                            <div class="space-y-1">
                                <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-white px-3 py-1 text-sm font-medium text-emerald-700">
                                    <span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span>
                                    Cuenta vinculada con Google
                                </div>
                                <p class="text-sm text-gray-700">
                                    Esta cuenta ya puede ingresar con Google sin perder la identidad local asociada al documento.
                                </p>
                                <p class="text-xs text-gray-600">
                                    Correo vinculado: <span class="font-medium text-gray-800">{{ auth()->user()->email }}</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg border border-base-300">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="max-w-2xl space-y-1">
                            <h3 class="text-lg font-semibold text-gray-900">Vincular Google</h3>
                            <p class="text-sm text-gray-600">
                                Sumá Google como método adicional de acceso para esta misma cuenta. Vas a mantener tu documento y tu contraseña local, pero también vas a poder entrar con OAuth.
                            </p>
                        </div>

                        <a href="{{ route('auth.google.link-existing') }}" class="btn btn-outline gap-2 whitespace-nowrap">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" aria-hidden="true">
                                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/>
                                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                            </svg>
                            Vincular Google
                        </a>
                    </div>
                </div>
            @endif

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <livewire:profile.update-profile-information-form />
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <livewire:profile.update-password-form />
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <livewire:profile.delete-user-form />
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
