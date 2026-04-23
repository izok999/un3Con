@props([
    'value' => null,
    'icon' => null,
    'color' => '',
    'title' => null,
    'description' => null,
    'tooltip' => null,
    'tooltipLeft' => null,
    'tooltipRight' => null,
    'tooltipBottom' => null,
])

<x-stat
    :value="$value"
    :icon="$icon"
    :color="$color"
    :title="$title"
    :description="$description"
    :tooltip="$tooltip"
    :tooltip-left="$tooltipLeft"
    :tooltip-right="$tooltipRight"
    :tooltip-bottom="$tooltipBottom"
    {{ $attributes }}
>
    {{ $slot }}
</x-stat>