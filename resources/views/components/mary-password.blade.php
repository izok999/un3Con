@props([
    'label' => null,
    'hint' => null,
    'right' => false,
    'firstErrorOnly' => false,
])

<x-password
    :label="$label"
    :hint="$hint"
    :right="$right"
    :first-error-only="$firstErrorOnly"
    {{ $attributes }}
/>
