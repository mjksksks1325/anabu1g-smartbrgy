@extends('layouts.portal')
@section('title', __('Account assistance'))
@section('content')
<x-portal.auth-shell title="Hindi magamit ang online services" title-id="assistance-title">
<section class="card rp-form-card" aria-labelledby="assistance-title">
    <div class="card-body stack">
        <p class="alert alert-yellow" role="alert" data-portal-message>{{ __($message) }}</p>
        <p><span data-portal-i18n="Dalhin ang valid ID sa Barangay Hall para ma-check ng staff ang account at resident record ninyo.">{{ __('Dalhin ang valid ID sa Barangay Hall para ma-check ng staff ang account at resident record ninyo.') }}</span></p>
        <div class="btn-row"><a class="btn btn-outline" href="{{ route('portal.information') }}#help"><span data-portal-i18n="Get help">{{ __('Get help') }}</span></a></div>
    </div>
</section>
</x-portal.auth-shell>
@endsection
