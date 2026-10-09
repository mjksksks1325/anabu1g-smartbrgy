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
        <h3>@if($official['full_name'] === 'Barangay Official')<span data-portal-i18n="Barangay Official">{{ __('Barangay Official') }}</span>@else{{ $official['full_name'] }}@endif</h3>
        <p>@if($official['position'] === 'Position')<span data-portal-i18n="Position">{{ __('Position') }}</span>@else{{ $official['position'] }}@endif</p>
    </div>
    <details>
        <summary class="btn btn-outline btn-small"><span data-portal-i18n="View Details">{{ __('View Details') }}</span><span class="sr-only">: @if($official['full_name'] === 'Barangay Official')<span data-portal-i18n="Barangay Official">{{ __('Barangay Official') }}</span>@else{{ $official['full_name'] }}@endif</span></summary>
        <p class="next-note">@if($official['term'] === 'Term of Office')<span data-portal-i18n="Term of Office">{{ __('Term of Office') }}</span>@else{{ $official['term'] }}@endif</p>
    </details>
</article>
