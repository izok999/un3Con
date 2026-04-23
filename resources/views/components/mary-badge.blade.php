@props([
    'value' => null,
    'icon' => null,
    'iconRight' => null,
])

<x-badge
    :value="$value"
    :icon="$icon"
    :icon-right="$iconRight"
    {{ $attributes }}
>
    {{ $slot }}
</x-badge>