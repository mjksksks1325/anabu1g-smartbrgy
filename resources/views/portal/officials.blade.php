@extends('layouts.portal')
@section('title', __('Barangay officials'))
@section('band')
<x-portal.page-band title="Barangay officials" title-id="officials-title" parent="Requirements and fees" :parent-url="route('portal.information')">
    <p><span data-portal-i18n="Mga placeholder profile habang hinihintay ang opisyal na listahan mula sa barangay.">{{ __('Mga placeholder profile habang hinihintay ang opisyal na listahan mula sa barangay.') }}</span></p>
</x-portal.page-band>
@endsection
@section('content')
<section class="section section-first" id="officials" aria-labelledby="officials-title">
    <div class="two-column">
        @foreach($officials as $official)
            <x-portal.official-card :official="$official" />
        @endforeach
    </div>
</section>
@endsection
