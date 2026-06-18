@php
    $currentLocale = app()->getLocale();
    $locales = [
        'es' => ['flag' => '🇵🇾', 'label' => 'ES'],
        'en' => ['flag' => '🇺🇸', 'label' => 'EN'],
        'pt' => ['flag' => '🇧🇷', 'label' => 'PT'],
        'gn' => ['flag' => '🇵🇾', 'label' => 'GN'],
    ];
    $current = $locales[$currentLocale] ?? $locales['es'];
@endphp

<div class="dropdown dropdown-end">
    <div tabindex="0" role="button" class="btn btn-ghost btn-sm gap-1 text-sm">
        <span>{{ $current['flag'] }}</span>
        <span class="hidden sm:inline">{{ $current['label'] }}</span>
        <svg class="w-3 h-3 opacity-60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
        </svg>
    </div>
    <ul tabindex="0" class="dropdown-content menu glass-surface rounded-2xl z-100 w-36 p-2 shadow-lg">
        @foreach ($locales as $code => $locale)
            <li>
                <form method="POST" action="{{ route('locale.switch') }}">
                    @csrf
                    <input type="hidden" name="locale" value="{{ $code }}">
                    <button type="submit" class="flex items-center gap-2 w-full {{ $currentLocale === $code ? 'font-semibold text-primary' : '' }}">
                        <span>{{ $locale['flag'] }}</span>
                        <span>{{ $locale['label'] }}</span>
                        @if ($currentLocale === $code)
                            <span class="ml-auto">✓</span>
                        @endif
                    </button>
                </form>
            </li>
        @endforeach
    </ul>
</div>
