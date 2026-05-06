<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="uneTheme">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? config('app.name') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <script>const t=localStorage.getItem('une-theme');if(t)document.documentElement.setAttribute('data-theme',t);</script>
    </head>
    <body class="min-h-screen font-sans antialiased bg-app-pattern text-base-content">
        @php
            $isAlumno = auth()->user()?->hasRole('ALUMNO') ?? false;
            $isAdmin = auth()->user()?->hasRole('ADMIN') ?? false;

            $navigationLinks = [
                'home' => [
                    'title' => 'Inicio',
                    'icon' => 'o-home',
                    'route' => 'dashboard',
                    'active' => ['dashboard'],
                ],
                'carreras' => [
                    'title' => 'Mis Carreras',
                    'mobile_title' => 'Carreras',
                    'icon' => 'o-building-library',
                    'route' => 'alumno.carreras',
                    'active' => ['alumno.carreras', 'alumno.carreras.*'],
                ],
                'extracto' => [
                    'title' => 'Extracto Académico',
                    'mobile_title' => 'Extracto',
                    'icon' => 'o-document-text',
                    'route' => 'alumno.extracto',
                    'active' => ['alumno.extracto'],
                ],
                'materias' => [
                    'title' => 'Materias Inscriptas',
                    'mobile_title' => 'Materias',
                    'icon' => 'o-book-open',
                    'route' => 'alumno.materias',
                    'active' => ['alumno.materias'],
                ],
                'deudas' => [
                    'title' => 'Mis Deudas',
                    'mobile_title' => 'Pagos',
                    'icon' => 'o-currency-dollar',
                    'route' => 'alumno.deudas',
                    'active' => ['alumno.deudas'],
                ],
            ];

            $alumnoSidebarSections = [
                [
                    'title' => 'Académico',
                    'icon' => 'o-academic-cap',
                    'items' => ['carreras', 'extracto', 'materias'],
                ],
                [
                    'title' => 'Finanzas',
                    'icon' => 'o-banknotes',
                    'items' => ['deudas'],
                ],
            ];

            $alumnoMobileNavigation = ['home', 'carreras', 'extracto', 'materias', 'deudas'];
        @endphp

        <x-main full-width>
            <x-slot:sidebar drawer="main-drawer" class="glass-sidebar">
                {{-- Logo y nombre --}}
                <div class="p-4 text-center border-b border-base-300">
                    <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-primary/10 mb-2">
                        <svg class="w-8 h-8 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                        </svg>
                    </div>
                    <h2 class="font-bold text-primary text-sm">{{ config('app.name') }}</h2>
                    <p class="text-xs text-base-content/50">Portal del Estudiante</p>
                </div>

                <x-menu activate-by-route class="mt-2">
                    <x-menu-item
                        title="{{ $navigationLinks['home']['title'] }}"
                        icon="{{ $navigationLinks['home']['icon'] }}"
                        link="{{ route($navigationLinks['home']['route']) }}"
                    />

                    @if ($isAlumno)
                        @foreach ($alumnoSidebarSections as $section)
                            <x-menu-sub title="{{ $section['title'] }}" icon="{{ $section['icon'] }}">
                                @foreach ($section['items'] as $itemKey)
                                    @php
                                        $item = $navigationLinks[$itemKey];
                                    @endphp
                                    <x-menu-item title="{{ $item['title'] }}" icon="{{ $item['icon'] }}" link="{{ route($item['route']) }}" />
                                @endforeach
                            </x-menu-sub>
                        @endforeach
                    @endif

                    @if ($isAdmin)
                        <x-menu-separator />
                        <x-menu-sub title="Administración" icon="o-cog-6-tooth">
                            <x-menu-item title="Dashboard Admin" icon="o-chart-bar" link="{{ route('admin.dashboard') }}" />
                            <x-menu-item title="Consulta Alumnos" icon="o-magnifying-glass" link="{{ route('admin.consulta-alumno') }}" />
                        </x-menu-sub>
                    @endif

                    <x-menu-separator />
                    <x-menu-item title="Mi Perfil" icon="o-user" link="{{ route('profile') }}" />
                </x-menu>
            </x-slot:sidebar>

            <x-slot:content>
                {{-- Topbar --}}
                <div id="main-topbar" class="navbar glass-navbar sticky top-0 z-50 mb-4">
                    @unless ($isAlumno)
                        <label for="main-drawer" class="lg:hidden btn btn-ghost btn-sm">
                            <x-icon name="o-bars-3" />
                        </label>
                    @endunless
                    <div class="flex-1">
                        <span class="text-lg font-semibold">{{ $header ?? '' }}</span>
                    </div>
                    <div class="flex-none gap-2">
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
                            <div class="dropdown dropdown-end">
                                <div tabindex="0" role="button" class="btn btn-ghost btn-sm gap-2">
                                    <div class="avatar placeholder">
                                        <div class="bg-primary text-primary-content rounded-full w-8">
                                            <span class="text-xs">{{ substr(auth()->user()->name, 0, 2) }}</span>
                                        </div>
                                    </div>
                                    <span class="hidden sm:inline text-sm">{{ auth()->user()->name }}</span>
                                </div>
                                <ul tabindex="0" class="dropdown-content menu glass-surface rounded-2xl z-10 w-52 p-2">
                                    <li><a href="{{ route('profile') }}" wire:navigate>Mi Perfil</a></li>
                                    <li>
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit" class="w-full text-left">Cerrar sesión</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        @endauth
                    </div>
                </div>

                <div
                    data-route-shell
                    @class([
                        'route-transition-shell',
                        'mobile-bottom-nav-offset' => $isAlumno,
                    ])
                >
                    {{ $slot }}
                </div>
            </x-slot:content>
        </x-main>

        @if ($isAlumno)
            <nav
                data-testid="alumno-mobile-bottom-nav"
                aria-label="Navegacion principal del alumno"
                class="mobile-bottom-nav glass-navbar fixed inset-x-4 z-[60] lg:hidden"
            >
                <div class="grid grid-cols-5 gap-1 p-2">
                    @foreach ($alumnoMobileNavigation as $itemKey)
                        @php
                            $item = $navigationLinks[$itemKey];
                            $isActive = request()->routeIs(...$item['active']);
                        @endphp
                        <a
                            href="{{ route($item['route']) }}"
                            data-nav-context="mobile"
                            data-route-link="mobile-{{ $itemKey }}"
                            wire:navigate
                            @if ($isActive) aria-current="page" @endif
                            @class([
                                'mobile-bottom-nav-link flex flex-col items-center justify-center gap-1 px-2 py-2 text-center text-[0.68rem] font-semibold leading-tight transition',
                                'bg-primary text-primary-content shadow-sm shadow-primary/30' => $isActive,
                                'text-base-content/70 hover:bg-base-100/60 hover:text-base-content' => ! $isActive,
                            ])
                        >
                            <x-icon :name="$item['icon']" class="h-5 w-5" />
                            <span>{{ $item['mobile_title'] ?? $item['title'] }}</span>
                        </a>
                    @endforeach
                </div>
            </nav>
        @endif

        <x-toast />
    </body>
</html>
