@php
    $theme = request()->cookie('une-theme');
    $theme = in_array($theme, ['uneTheme', 'uneThemeDark'], true) ? $theme : 'uneTheme';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $theme }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <script>
            (() => {
                const allowedThemes = ['uneTheme', 'uneThemeDark'];
                const serializedThemeCookie = document.cookie
                    .split('; ')
                    .find((cookie) => cookie.startsWith('une-theme='));
                const cookieTheme = serializedThemeCookie
                    ? decodeURIComponent(serializedThemeCookie.split('=').slice(1).join('='))
                    : null;
                const savedTheme = cookieTheme ?? localStorage.getItem('une-theme');

                if (! allowedThemes.includes(savedTheme)) {
                    return;
                }

                document.documentElement.setAttribute('data-theme', savedTheme);
                document.cookie = `une-theme=${encodeURIComponent(savedTheme)}; path=/; max-age=31536000; samesite=lax`;
            })();
        </script>
    </head>
    <body class="font-sans antialiased bg-app-pattern text-base-content">
        <div class="min-h-dvh flex flex-col sm:justify-center items-center pt-8 sm:pt-0 pb-8 sm:pb-0 relative">

            {{-- Canvas de partículas (fixed, detrás de todo) --}}
            <canvas id="login-particles" data-particles
                    class="fixed inset-0 w-full h-full pointer-events-none"
                    style="z-index:0"></canvas>

            {{-- Selector de idioma --}}
            <div class="absolute top-4 right-4" style="z-index:2">
                <x-locale-switcher />
            </div>

            {{-- Logo --}}
            <div class="mb-4 relative" style="z-index:1">
                <a href="/" wire:navigate>
                    <div class="flex flex-col items-center">
                        <div class="w-14 h-14 sm:w-12 sm:h-12 rounded-full bg-primary/10 ring-4 ring-primary/5 flex items-center justify-center">
                            <svg class="w-8 h-8 sm:w-7 sm:h-7 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                            </svg>
                        </div>
                        <span class="mt-2 text-sm font-bold tracking-tight text-primary">{{ config('app.name') }}</span>
                    </div>
                </a>
            </div>

            {{-- Card del formulario --}}
            <div class="w-full sm:max-w-md px-5 py-6 sm:py-5 overflow-hidden relative rounded-[1.5rem] bg-white/[0.04] border border-white/[0.14]" style="z-index:1;">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
