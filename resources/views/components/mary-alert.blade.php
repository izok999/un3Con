@props([
    'title' => null,
    'icon' => null,
    'description' => null,
    'shadow' => false,
    'dismissible' => false,
])

<x-alert
    :title="$title"
    :icon="$icon"
    :description="$description"
    :shadow="$shadow"
    :dismissible="$dismissible"
    {{ $attributes }}
>
    {{ $slot }}
</x-alert>