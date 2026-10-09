@extends('layouts.portal')
@section('title', __('Requirements and fees'))
@section('band')
<x-portal.page-band title="Requirements and fees">
    <p><span data-portal-i18n="Mga dokumentong puwedeng i-request online, ang fee na nasa system, at kung saan hihingi ng tulong. Puwede itong basahin kahit walang account.">{{ __('Mga dokumentong puwedeng i-request online, ang fee na nasa system, at kung saan hihingi ng tulong. Puwede itong basahin kahit walang account.') }}</span></p>
    <x-slot:below>
        <nav class="jump-links" data-portal-i18n-aria-label="Sa page na ito" aria-label="{{ __('Sa page na ito') }}">
            <a href="#requirements"><span data-portal-i18n="Mga dokumento">{{ __('Mga dokumento') }}</span></a>
            <a href="#help"><span data-portal-i18n="Get help">{{ __('Get help') }}</span></a>
            <a href="{{ route('portal.officials') }}"><span data-portal-i18n="Barangay officials">{{ __('Barangay officials') }}</span></a>
        </nav>
    </x-slot:below>
</x-portal.page-band>
@endsection
@section('content')
<section class="section section-first" id="requirements" aria-labelledby="requirements-title">
    <div class="section-head"><h2 id="requirements-title"><span data-portal-i18n="Mga dokumento">{{ __('Mga dokumento') }}</span></h2></div>
    <div class="requirements-intro">
        <div class="panel panel-tint">
            <h3><span data-portal-i18n="Para sa lahat ng dokumento">{{ __('Para sa lahat ng dokumento') }}</span></h3>
            <ul class="plain-list">
                <li><span data-portal-i18n="Kailangan ng resident account para mag-request online.">{{ __('Kailangan ng resident account para mag-request online.') }}</span></li>
                <li><span data-portal-i18n="Nire-review ng barangay staff ang bawat request. May mga dokumentong posibleng mangailangan ng dagdag na verification bago i-release.">{{ __('Nire-review ng barangay staff ang bawat request. May mga dokumentong posibleng mangailangan ng dagdag na verification bago i-release.') }}</span></li>
                <li><span data-portal-i18n="Sa Barangay Hall kukunin ang dokumento. Dalhin ang valid ID at ang reference number.">{{ __('Sa Barangay Hall kukunin ang dokumento. Dalhin ang valid ID at ang reference number.') }}</span></li>
                <li><span data-portal-i18n="Sa Barangay Hall babayaran ang fee. Valid ang online request nang 30 araw mula sa pag-submit.">{{ __('Sa Barangay Hall babayaran ang fee. Valid ang online request nang 30 araw mula sa pag-submit.') }}</span></li>
            </ul>
        </div>
        <p class="unconfirmed"><strong><span data-portal-i18n="Hindi pa kumpirmado:">{{ __('Hindi pa kumpirmado:') }}</span></strong> <span data-portal-i18n="Business Clearance fee, eksaktong requirements, at processing time. Regular Barangay ID ang fee na nakalista. Magtanong sa Barangay Hall bago kumuha.">{{ __('Business Clearance fee, eksaktong requirements, at processing time. Regular Barangay ID ang fee na nakalista. Magtanong sa Barangay Hall bago kumuha.') }}</span></p>
    </div>
    <div class="service-table">
    <div class="service-list-head" aria-hidden="true"><span><span data-portal-i18n="Dokumento">{{ __('Dokumento') }}</span></span><span><span data-portal-i18n="Fee">{{ __('Fee') }}</span></span><span></span></div>
    <ul class="service-list">
        @foreach($services as $service)
            <li class="service-item">
                <h3><span data-portal-i18n="{{ $service->value }}">{{ __($service->value) }}</span></h3>
                <p class="service-fee"><span class="service-fee-label"><span data-portal-i18n="Fee:">{{ __('Fee:') }}</span> </span><span data-portal-i18n="{{ $service->feeLabel() }}">{{ __($service->feeLabel()) }}</span></p>
                <a class="btn btn-outline btn-small" href="{{ route('portal.request.create', ['service' => $service->portalCode()]) }}" aria-label="Request <span data-portal-i18n="{{ $service->value }}">{{ __($service->value) }}</span>"><span data-portal-i18n="Request">{{ __('Request') }}</span></a>
            </li>
        @endforeach
    </ul>
    </div>
</section>

<section class="section" id="help" aria-labelledby="help-title">
    <div class="section-head"><h2 id="help-title"><span data-portal-i18n="Get help">{{ __('Get help') }}</span></h2></div>
    <div class="two-column">
        <div class="panel">
            <h3><span data-portal-i18n="Problema sa account o record">{{ __('Problema sa account o record') }}</span></h3>
            <p><span data-portal-i18n="Pumunta sa Barangay Hall at dalhin ang valid ID. Matutulungan kayo ng staff sa:">{{ __('Pumunta sa Barangay Hall at dalhin ang valid ID. Matutulungan kayo ng staff sa:') }}</span></p>
            <ul class="plain-list help-list">
                <li><span data-portal-i18n="record na hindi mahanap sa">{{ __('record na hindi mahanap sa') }}</span> <a href="{{ route('portal.register') }}"><span data-portal-i18n="Create account">{{ __('Create account') }}</span></a></li>
                <li><span data-portal-i18n="maling pangalan, address, o contact number">{{ __('maling pangalan, address, o contact number') }}</span></li>
                <li><span data-portal-i18n="activation code kung hindi dumating ang email">{{ __('activation code kung hindi dumating ang email') }}</span></li>
                <li><span data-portal-i18n="account na hindi magamit ang online services">{{ __('account na hindi magamit ang online services') }}</span></li>
            </ul>
            <p class="next-note"><span data-portal-i18n="Nakalimutan ang password?">{{ __('Nakalimutan ang password?') }}</span> <a href="{{ route('password.request') }}"><span data-portal-i18n="I-reset ang password">{{ __('I-reset ang password') }}</span></a>.</p>
        </div>
        <div class="panel panel-tint">
            <h3><span data-portal-i18n="Barangay office">{{ __('Barangay office') }}</span></h3>
            <dl class="office-list">
                <dt><span data-portal-i18n="Address">{{ __('Address') }}</span></dt><dd><span data-portal-i18n="Barangay Anabu I-G, Lungsod ng Imus, Cavite">{{ __('Barangay Anabu I-G, Lungsod ng Imus, Cavite') }}</span></dd>
                <dt><span data-portal-i18n="Telepono">{{ __('Telepono') }}</span></dt><dd><span class="tag-unconfirmed"><span data-portal-i18n="Hindi pa kumpirmado">{{ __('Hindi pa kumpirmado') }}</span></span></dd>
                <dt><span data-portal-i18n="Office hours">{{ __('Office hours') }}</span></dt><dd><span class="tag-unconfirmed"><span data-portal-i18n="Hindi pa kumpirmado">{{ __('Hindi pa kumpirmado') }}</span></span></dd>
            </dl>
            <p class="next-note"><span data-portal-i18n="Para sa emergency, tingnan ang">{{ __('Para sa emergency, tingnan ang') }}</span> <a href="{{ route('home') }}#contacts"><span data-portal-i18n="emergency hotlines">{{ __('emergency hotlines') }}</span></a>.</p>
        </div>
    </div>
</section>

<section class="section" aria-labelledby="resources-title">
    <div class="section-head"><h2 id="resources-title"><span data-portal-i18n="Official resources and assistance">{{ __('Official resources and assistance') }}</span></h2></div>
    <div class="two-column">
        @foreach($resources as $resource)
            <article class="panel">
                <h3><span data-portal-i18n="{{ $resource['title'] }}">{{ __($resource['title']) }}</span></h3>
                <p><span data-portal-i18n="{{ $resource['description'] }}">{{ __($resource['description']) }}</span></p>
                <p class="next-note"><a class="btn btn-outline btn-small" href="{{ $resource['url'] }}" target="_blank" rel="noopener noreferrer"><span data-portal-i18n="{{ $resource['link_label'] }}">{{ __($resource['link_label']) }}</span> <span class="sr-only"><span data-portal-i18n="(opens in a new tab)">{{ __('(opens in a new tab)') }}</span></span></a></p>
            </article>
        @endforeach
    </div>
</section>
@endsection
