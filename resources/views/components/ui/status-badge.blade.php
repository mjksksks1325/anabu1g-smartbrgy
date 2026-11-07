{{--
    Status badge with a semantic tone: success, warning, danger, info, neutral, brand.
    The text always names the state; the colour only reinforces it.
--}}
@props([
    'tone' => 'neutral',
])

@php
    $color = match ($tone) {
        'success' => 'green',
        'warning' => 'amber',
        'danger' => 'red',
        'info' => 'sky',
        'brand' => 'emerald',
        default => 'zinc',
    };
@endphp

<flux:badge size="sm" :color="$color" :attributes="$attributes->merge(['data-ui-status' => $tone])">{{ $slot }}</flux:badge>
