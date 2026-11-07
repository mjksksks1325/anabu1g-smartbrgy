@props(['document'])
{{-- The resident photo captured at issuance. Nothing is drawn when there is none: no stand-in avatar. --}}
@if ($document->photoUrl())
    <img {{ $attributes->class('photo') }} data-keep-clear src="{{ $document->photoUrl() }}" alt="Resident photo at issuance">
@endif
