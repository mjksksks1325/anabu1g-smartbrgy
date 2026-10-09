@extends('layouts.portal')
@section('title', __('Account assistance'))
@section('band')
<x-portal.page-band title="Hindi magamit ang online services" title-id="assistance-title" />
@endsection
@section('content')
<section class="card card-accent narrow" aria-labelledby="assistance-title">
    <div class="card-body stack">
        <p class="alert alert-yellow" role="alert" data-portal-message>{{ __($message) }}</p>
        <p><span data-portal-i18n="Dalhin ang valid ID sa Barangay Hall para ma-check ng staff ang account at resident record ninyo.">{{ __('Dalhin ang valid ID sa Barangay Hall para ma-check ng staff ang account at resident record ninyo.') }}</span></p>
        <div class="btn-row"><a class="btn btn-outline" href="{{ route('portal.information') }}#help"><span data-portal-i18n="Get help">{{ __('Get help') }}</span></a></div>
    </div>
</section>
@endsection
