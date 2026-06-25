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
                        <div class="w-20 h-20 sm:w-[72px] sm:h-[72px] rounded-full bg-primary/10 ring-4 ring-primary/5 flex items-center justify-center overflow-hidden">
                            <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }} logo" class="w-12 h-12 sm:w-10 sm:h-10 object-contain" />
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
