@extends('layouts.portal')
@section('title', 'Resident Portal')
@section('hero-header', 'true')
@php($residentSignedIn = auth('resident')->check())

@section('band')
<div class="portal-band">
    <div class="portal-band-inner">
        <section class="portal-hero" aria-labelledby="portal-title">
            <p class="eyebrow">E-government resident services</p>
            <h1 id="portal-title">Mabilis. Malinaw. Maaasahang serbisyo.</h1>
            <p>Isang opisyal at ligtas na portal para mag-request ng dokumento, subaybayan ang status, at makakuha ng impormasyon mula sa Barangay Anabu I-G.</p>
            <div class="portal-hero-actions">
                <a class="btn btn-green" href="{{ route('portal.request.create') }}">Request a document <span aria-hidden="true">&#8594;</span></a>
                <a class="btn btn-hero-outline" href="{{ $residentSignedIn ? route('portal.account') : route('portal.login') }}">Check status</a>
            </div>
            @unless($residentSignedIn)
                <p class="portal-hero-account">Government-secured resident portal &middot; Wala pang account? <a href="{{ route('portal.register') }}">Create one</a></p>
            @endunless
        </section>
        <figure class="portal-hero-photo"><img src="{{ asset('images/barangay-hall-anabu-1g.jpg') }}" width="1536" height="1024" alt="Barangay Hall ng Anabu I-G"><figcaption>Barangay Anabu I-G Hall</figcaption></figure>
    </div>
</div>
@endsection

@section('content')
<section class="digital-services" aria-labelledby="tasks-title">
    <h2 id="tasks-title" class="sr-only">Online services</h2>
    <div class="portal-service-grid">
        <a class="portal-service-card" href="{{ $residentSignedIn ? route('portal.account') : route('portal.login') }}">
            <span class="service-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/><path d="M12 7v5l3.5 2"/></svg></span>
            <strong>My requests</strong><span>Tingnan ang status at release date</span>
        </a>
        <a class="portal-service-card" href="{{ route('portal.information') }}#requirements">
            <span class="service-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 9h8M8 13h8M8 17h4"/></svg></span>
            <strong>Requirements &amp; fees</strong><span>Alamin ang kailangan at bayarin</span>
        </a>
        <a class="portal-service-card" href="{{ route('portal.information') }}#help">
            <span class="service-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/><path d="M9.5 9a2.5 2.5 0 0 1 5 0c0 2-2.5 2-2.5 4M12 16.5h.01"/></svg></span>
            <strong>Get help</strong><span>Account at resident record support</span>
        </a>
    </div>
</section>
<div class="service-info">
    <section class="howto" aria-labelledby="howto-title">
        <p class="eyebrow">Madaling proseso</p>
        <h2 id="howto-title">Paano mag-request</h2>
        <p>Apat na simpleng hakbang mula online request hanggang pagkuha ng dokumento.</p>
        <ol class="howto-steps">
            @if($residentSignedIn)
                <li><strong>Naka-log in na kayo.</strong> Puwede nang mag-request ng dokumento.</li>
            @else
                <li><strong>Mag-log in.</strong> Kailangan ng resident account na naka-link sa record ninyo sa barangay.</li>
            @endif
            <li><strong>Piliin ang dokumento at i-submit.</strong> I-review ang details bago i-submit. Makakatanggap kayo ng reference number.</li>
            <li><strong>Bantayan ang status.</strong> Makikita sa My requests kung Ready for release na.</li>
            <li><strong>Kunin sa Barangay Hall.</strong> Dalhin ang original na valid ID at ang reference number. Doon din babayaran ang fee, kung mayroon. Walang online payment.</li>
        </ol>
        <p class="howto-note">Valid nang 30 araw ang online request mula sa araw na na-submit ito.</p>
    </section>

    <section class="doc-rates" aria-labelledby="doc-rates-title">
        <p class="eyebrow">Transparent na bayarin</p>
        <div class="doc-rates-head">
            <h2 id="doc-rates-title">Mga dokumento at fee</h2>
            <span class="tag-unconfirmed">Business Clearance fee: To be confirmed</span>
        </div>
        <table class="doc-rates-table">
            <caption class="sr-only">Fee ng bawat dokumento ayon sa system</caption>
            <thead><tr><th scope="col">Dokumento</th><th scope="col">Fee</th></tr></thead>
            <tbody>
                @foreach($services as $service)
                    <tr><th scope="row">{{ $service->value }}</th><td>{{ $service->feeLabel() }}</td></tr>
                @endforeach
            </tbody>
        </table>
        <p class="doc-rates-note">PHP 25 ang barangay clearance at certificate of residency. Libre ang indigency at first-time jobseeker. Regular Barangay ID: PHP 100. Hinihintay pa ang kumpirmasyon sa Business Clearance fee, requirements, at processing time. <a href="{{ route('portal.information') }}#requirements">Buong requirements at fees</a></p>
    </section>
</div>

<section class="home-section announcements-section" id="announcements" aria-labelledby="announcements-title">
    <div class="announcements-intro"><p class="eyebrow">Public information</p><h2 id="announcements-title">Announcements</h2><p>Mga opisyal na update mula sa barangay at Lungsod ng Imus.</p></div>
    <div class="notice-row">
        <article class="notice-callout">
            <h3>Barangay Anabu I-G</h3>
            <p>Wala pang inilalathalang anunsiyo ang Barangay Anabu I-G sa portal na ito. Bumalik dito para sa mga susunod na update.</p>
        </article>
        <article class="notice-callout">
            <h3>Lungsod ng Imus</h3>
            <p>Nasa opisyal na website ng City of Imus ang mga balita at abiso ng lungsod. <a href="https://cityofimus.gov.ph/" target="_blank" rel="noopener noreferrer">Buksan ang City of Imus website</a></p>
        </article>
    </div>
</section>

<section class="home-section office-section" id="office" aria-labelledby="office-title">
    <div class="office-details">
        <h2 id="office-title">Barangay Hall</h2>
        <p class="office-intro">Dito kukunin ang mga dokumento at dito rin magpapatulong sa account o record.</p>
        <dl class="info-list">
            <div><dt>Address</dt><dd>Barangay Anabu I-G, Lungsod ng Imus, Cavite</dd></div>
            <div><dt>Telepono</dt><dd><span class="tag-unconfirmed">Hindi pa kumpirmado</span></dd></div>
            <div><dt>Office hours</dt><dd><span class="tag-unconfirmed">Hindi pa kumpirmado</span></dd></div>
            <div><dt>Land Area</dt><dd><span class="tag-unconfirmed">To be confirmed</span></dd></div>
            <div id="population"><dt>Populasyon</dt><dd>2,345 na residente ayon sa 2024 Census of Population ng PSA. <a href="https://psa.gov.ph/classification/psgc/barangays/0402109000" target="_blank" rel="noopener noreferrer">PSA data</a></dd></div>
        </dl>
        <p class="office-note">Habang wala pang kumpirmadong numero at oras, pumunta nang personal sa Barangay Hall.</p>
    </div>
    <div class="office-map" id="location">
        <div class="map-panel">
            <iframe title="Mapa ng Barangay Anabu I-G, Imus, Cavite" src="https://maps.google.com/maps?q=Barangay%20Anabu%20I-G%2C%20Imus%2C%20Cavite&amp;output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            <p>Area ng Barangay Anabu I-G. <span class="tag-unconfirmed">Hindi pa kumpirmado</span> ang eksaktong entrance ng Barangay Hall. <a href="https://www.google.com/maps/search/?api=1&amp;query=Barangay+Anabu+I-G%2C+Imus%2C+Cavite" target="_blank" rel="noopener noreferrer">Buksan sa Google Maps</a></p>
        </div>
    </div>
</section>

<section class="home-section hotline-panel" id="contacts" aria-labelledby="contacts-title">
    <div class="hotline-head">
        <h2 id="contacts-title">Emergency hotlines</h2>
        <p>Mula sa <a href="https://cityofimus.gov.ph/" target="_blank" rel="noopener noreferrer">City of Imus website</a></p>
    </div>
    <ul class="hotline-list">
        <li><span>National emergency hotline</span><a href="tel:911">911</a></li>
        <li><span>City of Imus emergency</span><a href="tel:+63468889911">(046) 888 9911</a></li>
        <li><span>Imus PNP</span><a href="tel:+639985985601">0998 598 5601</a></li>
        <li><span>Bureau of Fire Protection</span><a href="tel:+639155283256">0915 528 3256</a></li>
    </ul>
</section>
@endsection
