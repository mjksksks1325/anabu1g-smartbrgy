@props(['official'])

@php
    $photoPath = $official['photo_path'] ?? null;
    $photoUrl = is_string($photoPath) && $photoPath !== '' && is_file(public_path($photoPath))
        ? asset($photoPath)
        : asset('images/official-placeholder.svg');
@endphp

<article {{ $attributes->class(['panel', 'stack']) }}>
    <img src="{{ $photoUrl }}" width="96" height="96" alt="" loading="lazy">
    <div>
        <h3>{{ $official['full_name'] }}</h3>
        <p>{{ $official['position'] }}</p>
    </div>
    <details>
        <summary class="btn btn-outline btn-small">View Details<span class="sr-only">: {{ $official['full_name'] }}</span></summary>
        <p class="next-note">{{ $official['term'] }}</p>
    </details>
</article>
