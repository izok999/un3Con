<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="uneTheme">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }} — Portal del Estudiante</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <script>const t=localStorage.getItem('une-theme');if(t)document.documentElement.setAttribute('data-theme',t);</script>
    </head>
    <body class="font-sans antialiased bg-app-pattern text-base-content">

        {{-- Navbar --}}
        <div class="navbar glass-navbar sticky top-0 z-50" id="main-topbar">
            <div class="navbar-start">
                <span class="text-xl font-bold tracking-wide px-4">🎓 {{ config('app.name') }}</span>
            </div>
            <div class="navbar-end px-4 gap-2">
                {{-- Toggle modo oscuro --}}
                <label class="swap swap-rotate btn btn-ghost btn-sm">
                    <input type="checkbox" id="theme-toggle" />
                    <svg class="swap-off w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                    </svg>
                    <svg class="swap-on w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                    </svg>
                </label>
                @auth
                    <a href="{{ url('/dashboard') }}" class="btn btn-ghost btn-sm">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-ghost btn-sm">Iniciar sesión</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="btn btn-secondary btn-sm ml-2">Registrarse</a>
                    @endif
                @endauth
            </div>
        </div>

        {{-- Hero --}}
        <div class="hero min-h-[70vh] bg-base-100/20 backdrop-blur-none">
            <div class="hero-content text-center">
                <div class="max-w-2xl">
                    <div class="mb-6">
                        <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-primary/10">
                            <svg class="w-14 h-14 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                            </svg>
                        </div>
                    </div>
                    <h1 class="text-4xl md:text-5xl font-bold text-base-content">
                        Portal del <span class="text-primary">Estudiante</span>
                    </h1>
                    <p class="py-6 text-lg text-base-content/70">
                        Consultá tus carreras, calificaciones, materias inscriptas y estado de cuenta desde un solo lugar.
                    </p>
                    @guest
                        <div class="flex flex-col sm:flex-row gap-3 justify-center">
                            <a href="{{ route('login') }}" class="btn btn-primary btn-lg">
                                <svg class="w-5 h-5 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" /></svg>
                                Iniciar sesión
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="btn btn-outline btn-primary btn-lg">
                                    Crear cuenta
                                </a>
                            @endif
                        </div>
                    @else
                        <a href="{{ url('/dashboard') }}" class="btn btn-primary btn-lg">
                            Ir al Dashboard
                        </a>
                    @endguest
                </div>
            </div>
        </div>

        {{-- Features --}}
        <div class="py-16">
            <div class="max-w-6xl mx-auto px-6">
                <h2 class="text-2xl font-bold text-center mb-10">¿Qué podés consultar?</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="card glass-card">
                        <div class="card-body items-center text-center">
                            <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center mb-2">
                                <svg class="w-6 h-6 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" /></svg>
                            </div>
                            <h3 class="card-title text-base">Mis Carreras</h3>
                            <p class="text-sm text-base-content/60">Habilitaciones vigentes, sede y periodo lectivo</p>
                        </div>
                    </div>
                    <div class="card glass-card">
                        <div class="card-body items-center text-center">
                            <div class="w-12 h-12 rounded-full bg-secondary/10 flex items-center justify-center mb-2">
                                <svg class="w-6 h-6 text-secondary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                            </div>
                            <h3 class="card-title text-base">Extracto Académico</h3>
                            <p class="text-sm text-base-content/60">Historial completo de calificaciones</p>
                        </div>
                    </div>
                    <div class="card glass-card">
                        <div class="card-body items-center text-center">
                            <div class="w-12 h-12 rounded-full bg-accent/10 flex items-center justify-center mb-2">
                                <svg class="w-6 h-6 text-accent" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>
                            </div>
                            <h3 class="card-title text-base">Materias Inscriptas</h3>
                            <p class="text-sm text-base-content/60">Materias del periodo, turno y sección</p>
                        </div>
                    </div>
                    <div class="card glass-card">
                        <div class="card-body items-center text-center">
                            <div class="w-12 h-12 rounded-full bg-error/10 flex items-center justify-center mb-2">
                                <svg class="w-6 h-6 text-error" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" /></svg>
                            </div>
                            <h3 class="card-title text-base">Estado de Cuenta</h3>
                            <p class="text-sm text-base-content/60">Deudas pendientes y saldos</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <footer class="footer footer-center p-6 bg-neutral text-neutral-content">
            <div>
                <p class="text-sm">{{ config('app.name') }} &copy; {{ date('Y') }} — Universidad Nacional del Este</p>
            </div>
        </footer>

    </body>
</html>
