<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="uneTheme">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <script>const t=localStorage.getItem('une-theme');if(t)document.documentElement.setAttribute('data-theme',t);</script>
    </head>
    <body class="font-sans antialiased bg-app-pattern text-base-content">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 relative overflow-hidden">

            {{-- Canvas de partículas (fondo, detrás de todo) --}}
            <canvas id="login-particles" data-particles
                    class="absolute inset-0 w-full h-full pointer-events-none"
                    style="z-index:0"></canvas>

            {{-- Logo --}}
            <div class="mb-4 relative" style="z-index:1">
                <a href="/" wire:navigate>
                    <div class="flex flex-col items-center">
                        <div class="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center">
                            <svg class="w-10 h-10 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                            </svg>
                        </div>
                        <span class="mt-2 text-lg font-bold text-primary">{{ config('app.name') }}</span>
                    </div>
                </a>
            </div>

            {{-- Card del formulario --}}
            <div class="w-full sm:max-w-md px-6 py-6 glass-card overflow-hidden relative" style="z-index:1;">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
