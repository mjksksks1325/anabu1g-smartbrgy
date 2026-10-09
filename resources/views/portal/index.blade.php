@extends('layouts.portal')
@section('title', __('Resident Portal'))
@section('hero-header', 'true')
@php($residentSignedIn = auth('resident')->check())

@section('band')
<div class="portal-band">
    <div class="portal-band-inner">
        <section class="portal-hero" aria-labelledby="portal-title">
            <p class="eyebrow"><span data-portal-i18n="E-government resident services">{{ __('E-government resident services') }}</span></p>
            <h1 id="portal-title"><span data-portal-i18n="Mabilis. Malinaw. Maaasahang serbisyo.">{{ __('Mabilis. Malinaw. Maaasahang serbisyo.') }}</span></h1>
            <p><span data-portal-i18n="Isang opisyal at ligtas na portal para mag-request ng dokumento, subaybayan ang status, at makakuha ng impormasyon mula sa Barangay Anabu I-G.">{{ __('Isang opisyal at ligtas na portal para mag-request ng dokumento, subaybayan ang status, at makakuha ng impormasyon mula sa Barangay Anabu I-G.') }}</span></p>
            <div class="portal-hero-actions">
                <a class="btn btn-green" href="{{ route('portal.request.create') }}"><span data-portal-i18n="Request a document">{{ __('Request a document') }}</span> <span aria-hidden="true">&#8594;</span></a>
                <a class="btn btn-hero-outline" href="{{ $residentSignedIn ? route('portal.account') : route('portal.login') }}"><span data-portal-i18n="Check status">{{ __('Check status') }}</span></a>
            </div>
            @unless($residentSignedIn)
                <p class="portal-hero-account"><span data-portal-i18n="Government-secured resident portal · Wala pang account?">{{ __('Government-secured resident portal · Wala pang account?') }}</span> <a href="{{ route('portal.register') }}"><span data-portal-i18n="Create one">{{ __('Create one') }}</span></a></p>
            @endunless
        </section>
        <figure class="portal-hero-photo"><img src="{{ asset('images/barangay-hall-anabu-1g.jpg') }}" width="1536" height="1024" data-portal-i18n-alt="Barangay Hall ng Anabu I-G" alt="{{ __('Barangay Hall ng Anabu I-G') }}"><figcaption><span data-portal-i18n="Barangay Anabu I-G Hall">{{ __('Barangay Anabu I-G Hall') }}</span></figcaption></figure>
    </div>
</div>
@endsection

@section('content')
<section class="digital-services" aria-labelledby="tasks-title">
    <h2 id="tasks-title" class="sr-only"><span data-portal-i18n="Online services">{{ __('Online services') }}</span></h2>
    <div class="portal-service-grid">
        <a class="portal-service-card" href="{{ $residentSignedIn ? route('portal.account') : route('portal.login') }}">
            <span class="service-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/><path d="M12 7v5l3.5 2"/></svg></span>
            <strong><span data-portal-i18n="My requests">{{ __('My requests') }}</span></strong><span><span data-portal-i18n="Tingnan ang status at release date">{{ __('Tingnan ang status at release date') }}</span></span>
        </a>
        <a class="portal-service-card" href="{{ route('portal.information') }}#requirements">
            <span class="service-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 9h8M8 13h8M8 17h4"/></svg></span>
            <strong><span data-portal-i18n="Requirements &amp; fees">{{ __('Requirements & fees') }}</span></strong><span><span data-portal-i18n="Alamin ang kailangan at bayarin">{{ __('Alamin ang kailangan at bayarin') }}</span></span>
        </a>
        <a class="portal-service-card" href="{{ route('portal.information') }}#help">
            <span class="service-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/><path d="M9.5 9a2.5 2.5 0 0 1 5 0c0 2-2.5 2-2.5 4M12 16.5h.01"/></svg></span>
            <strong><span data-portal-i18n="Get help">{{ __('Get help') }}</span></strong><span><span data-portal-i18n="Account at resident record support">{{ __('Account at resident record support') }}</span></span>
        </a>
    </div>
</section>
<div class="service-info">
    <section class="howto" aria-labelledby="howto-title">
        <p class="eyebrow"><span data-portal-i18n="Madaling proseso">{{ __('Madaling proseso') }}</span></p>
        <h2 id="howto-title"><span data-portal-i18n="Paano mag-request">{{ __('Paano mag-request') }}</span></h2>
        <p><span data-portal-i18n="Apat na simpleng hakbang mula online request hanggang pagkuha ng dokumento.">{{ __('Apat na simpleng hakbang mula online request hanggang pagkuha ng dokumento.') }}</span></p>
        <ol class="howto-steps">
            @if($residentSignedIn)
                <li><strong><span data-portal-i18n="Naka-log in na kayo.">{{ __('Naka-log in na kayo.') }}</span></strong> <span data-portal-i18n="Puwede nang mag-request ng dokumento.">{{ __('Puwede nang mag-request ng dokumento.') }}</span></li>
            @else
                <li><strong><span data-portal-i18n="Mag-log in.">{{ __('Mag-log in.') }}</span></strong> <span data-portal-i18n="Kailangan ng resident account na naka-link sa record ninyo sa barangay.">{{ __('Kailangan ng resident account na naka-link sa record ninyo sa barangay.') }}</span></li>
            @endif
            <li><strong><span data-portal-i18n="Piliin ang dokumento at i-submit.">{{ __('Piliin ang dokumento at i-submit.') }}</span></strong> <span data-portal-i18n="I-review ang details bago i-submit. Makakatanggap kayo ng reference number.">{{ __('I-review ang details bago i-submit. Makakatanggap kayo ng reference number.') }}</span></li>
            <li><strong><span data-portal-i18n="Bantayan ang status.">{{ __('Bantayan ang status.') }}</span></strong> <span data-portal-i18n="Makikita sa My requests kung Ready for release na.">{{ __('Makikita sa My requests kung Ready for release na.') }}</span></li>
            <li><strong><span data-portal-i18n="Kunin sa Barangay Hall.">{{ __('Kunin sa Barangay Hall.') }}</span></strong> <span data-portal-i18n="Dalhin ang original na valid ID at ang reference number. Doon din babayaran ang fee, kung mayroon. Walang online payment.">{{ __('Dalhin ang original na valid ID at ang reference number. Doon din babayaran ang fee, kung mayroon. Walang online payment.') }}</span></li>
        </ol>
        <p class="howto-note"><span data-portal-i18n="Valid nang 30 araw ang online request mula sa araw na na-submit ito.">{{ __('Valid nang 30 araw ang online request mula sa araw na na-submit ito.') }}</span></p>
    </section>

    <section class="doc-rates" aria-labelledby="doc-rates-title">
        <p class="eyebrow"><span data-portal-i18n="Transparent na bayarin">{{ __('Transparent na bayarin') }}</span></p>
        <div class="doc-rates-head">
            <h2 id="doc-rates-title"><span data-portal-i18n="Mga dokumento at fee">{{ __('Mga dokumento at fee') }}</span></h2>
            <span class="tag-unconfirmed"><span data-portal-i18n="Business Clearance fee: To be confirmed">{{ __('Business Clearance fee: To be confirmed') }}</span></span>
        </div>
        <table class="doc-rates-table">
            <caption class="sr-only"><span data-portal-i18n="Fee ng bawat dokumento ayon sa system">{{ __('Fee ng bawat dokumento ayon sa system') }}</span></caption>
            <thead><tr><th scope="col"><span data-portal-i18n="Dokumento">{{ __('Dokumento') }}</span></th><th scope="col"><span data-portal-i18n="Fee">{{ __('Fee') }}</span></th></tr></thead>
            <tbody>
                @foreach($services as $service)
                    <tr><th scope="row"><span data-portal-i18n="{{ $service->value }}">{{ __($service->value) }}</span></th><td><span data-portal-i18n="{{ $service->feeLabel() }}">{{ __($service->feeLabel()) }}</span></td></tr>
                @endforeach
            </tbody>
        </table>
        <p class="doc-rates-note"><span data-portal-i18n="PHP 25 ang barangay clearance at certificate of residency. Libre ang indigency at first-time jobseeker. Regular Barangay ID: PHP 100. Hinihintay pa ang kumpirmasyon sa Business Clearance fee, requirements, at processing time.">{{ __('PHP 25 ang barangay clearance at certificate of residency. Libre ang indigency at first-time jobseeker. Regular Barangay ID: PHP 100. Hinihintay pa ang kumpirmasyon sa Business Clearance fee, requirements, at processing time.') }}</span> <a href="{{ route('portal.information') }}#requirements"><span data-portal-i18n="Buong requirements at fees">{{ __('Buong requirements at fees') }}</span></a></p>
    </section>
</div>

<section class="home-section announcements-section" id="announcements" aria-labelledby="announcements-title">
    <div class="announcements-intro"><p class="eyebrow"><span data-portal-i18n="Public information">{{ __('Public information') }}</span></p><h2 id="announcements-title"><span data-portal-i18n="Announcements">{{ __('Announcements') }}</span></h2><p><span data-portal-i18n="Mga opisyal na update mula sa barangay at Lungsod ng Imus.">{{ __('Mga opisyal na update mula sa barangay at Lungsod ng Imus.') }}</span></p></div>
    <div class="notice-row">
        <article class="notice-callout">
            <h3><span data-portal-i18n="Barangay Anabu I-G">{{ __('Barangay Anabu I-G') }}</span></h3>
            <p><span data-portal-i18n="Wala pang inilalathalang anunsiyo ang Barangay Anabu I-G sa portal na ito. Bumalik dito para sa mga susunod na update.">{{ __('Wala pang inilalathalang anunsiyo ang Barangay Anabu I-G sa portal na ito. Bumalik dito para sa mga susunod na update.') }}</span></p>
        </article>
        <article class="notice-callout">
            <h3><span data-portal-i18n="Lungsod ng Imus">{{ __('Lungsod ng Imus') }}</span></h3>
            <p><span data-portal-i18n="Nasa opisyal na website ng City of Imus ang mga balita at abiso ng lungsod.">{{ __('Nasa opisyal na website ng City of Imus ang mga balita at abiso ng lungsod.') }}</span> <a href="https://cityofimus.gov.ph/" target="_blank" rel="noopener noreferrer"><span data-portal-i18n="Buksan ang City of Imus website">{{ __('Buksan ang City of Imus website') }}</span></a></p>
        </article>
    </div>
</section>

<section class="home-section office-section" id="office" aria-labelledby="office-title">
    <div class="office-details">
        <h2 id="office-title"><span data-portal-i18n="Barangay Hall">{{ __('Barangay Hall') }}</span></h2>
        <p class="office-intro"><span data-portal-i18n="Dito kukunin ang mga dokumento at dito rin magpapatulong sa account o record.">{{ __('Dito kukunin ang mga dokumento at dito rin magpapatulong sa account o record.') }}</span></p>
        <dl class="info-list">
            <div><dt><span data-portal-i18n="Address">{{ __('Address') }}</span></dt><dd><span data-portal-i18n="Barangay Anabu I-G, Lungsod ng Imus, Cavite">{{ __('Barangay Anabu I-G, Lungsod ng Imus, Cavite') }}</span></dd></div>
            <div><dt><span data-portal-i18n="Telepono">{{ __('Telepono') }}</span></dt><dd><span class="tag-unconfirmed"><span data-portal-i18n="Hindi pa kumpirmado">{{ __('Hindi pa kumpirmado') }}</span></span></dd></div>
            <div><dt><span data-portal-i18n="Office hours">{{ __('Office hours') }}</span></dt><dd><span class="tag-unconfirmed"><span data-portal-i18n="Hindi pa kumpirmado">{{ __('Hindi pa kumpirmado') }}</span></span></dd></div>
            <div><dt><span data-portal-i18n="Land Area">{{ __('Land Area') }}</span></dt><dd><span class="tag-unconfirmed"><span data-portal-i18n="To be confirmed">{{ __('To be confirmed') }}</span></span></dd></div>
            <div id="population"><dt><span data-portal-i18n="Populasyon">{{ __('Populasyon') }}</span></dt><dd><span data-portal-i18n="2,345 na residente ayon sa 2024 Census of Population ng PSA.">{{ __('2,345 na residente ayon sa 2024 Census of Population ng PSA.') }}</span> <a href="https://psa.gov.ph/classification/psgc/barangays/0402109000" target="_blank" rel="noopener noreferrer"><span data-portal-i18n="PSA data">{{ __('PSA data') }}</span></a></dd></div>
        </dl>
        <p class="office-note"><span data-portal-i18n="Habang wala pang kumpirmadong numero at oras, pumunta nang personal sa Barangay Hall.">{{ __('Habang wala pang kumpirmadong numero at oras, pumunta nang personal sa Barangay Hall.') }}</span></p>
    </div>
    <div class="office-map" id="location">
        <div class="map-panel">
            <iframe data-portal-i18n-title="Mapa ng Barangay Anabu I-G, Imus, Cavite" title="{{ __('Mapa ng Barangay Anabu I-G, Imus, Cavite') }}" src="https://maps.google.com/maps?q=Barangay%20Anabu%20I-G%2C%20Imus%2C%20Cavite&amp;output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            <p><span data-portal-i18n="Area ng Barangay Anabu I-G.">{{ __('Area ng Barangay Anabu I-G.') }}</span> <span class="tag-unconfirmed"><span data-portal-i18n="Hindi pa kumpirmado">{{ __('Hindi pa kumpirmado') }}</span></span> <span data-portal-i18n="ang eksaktong entrance ng Barangay Hall.">{{ __('ang eksaktong entrance ng Barangay Hall.') }}</span> <a href="https://www.google.com/maps/search/?api=1&amp;query=Barangay+Anabu+I-G%2C+Imus%2C+Cavite" target="_blank" rel="noopener noreferrer"><span data-portal-i18n="Buksan sa Google Maps">{{ __('Buksan sa Google Maps') }}</span></a></p>
        </div>
    </div>
</section>

<section class="home-section hotline-panel" id="contacts" aria-labelledby="contacts-title">
    <div class="hotline-head">
        <h2 id="contacts-title"><span data-portal-i18n="Emergency hotlines">{{ __('Emergency hotlines') }}</span></h2>
        <p><span data-portal-i18n="Mula sa">{{ __('Mula sa') }}</span> <a href="https://cityofimus.gov.ph/" target="_blank" rel="noopener noreferrer"><span data-portal-i18n="City of Imus website">{{ __('City of Imus website') }}</span></a></p>
    </div>
    <ul class="hotline-list">
        <li><span><span data-portal-i18n="National emergency hotline">{{ __('National emergency hotline') }}</span></span><a href="tel:911">911</a></li>
        <li><span><span data-portal-i18n="City of Imus emergency">{{ __('City of Imus emergency') }}</span></span><a href="tel:+63468889911">(046) 888 9911</a></li>
        <li><span><span data-portal-i18n="Imus PNP">{{ __('Imus PNP') }}</span></span><a href="tel:+639985985601">0998 598 5601</a></li>
        <li><span><span data-portal-i18n="Bureau of Fire Protection">{{ __('Bureau of Fire Protection') }}</span></span><a href="tel:+639155283256">0915 528 3256</a></li>
    </ul>
</section>
@endsection
