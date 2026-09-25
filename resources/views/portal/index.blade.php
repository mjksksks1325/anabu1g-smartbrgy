@extends('layouts.portal')
@section('title', 'Resident Portal')
@php($residentSignedIn = auth('resident')->check())

@section('band')
<div class="portal-band" style="--hall-photo:url('{{ asset('images/barangay-hall-anabu-1g.jpg') }}')">
    <div class="portal-band-inner">
        <section class="portal-hero" aria-labelledby="portal-title">
            <h1 id="portal-title">Resident services</h1>
            <p>Mag-request ng barangay document online, tapos kunin ito sa Barangay Hall kapag Ready for release na. Dito rin makikita ang status ng bawat request ninyo.</p>
            @unless($residentSignedIn)
                <p class="portal-hero-account">Wala pang account? <a href="{{ route('portal.register') }}">Create account</a> gamit ang record ninyo sa barangay.</p>
            @endunless
        </section>

        <section class="portal-tasks" aria-labelledby="tasks-title">
            <h2 id="tasks-title" class="sr-only">Ano ang gusto ninyong gawin?</h2>
            <div class="portal-service-grid">
                <a class="portal-service-card portal-service-primary" href="{{ route('portal.request.create') }}">
                    <strong>Request a document</strong>
                    <span>Barangay Clearance, Certificate of Residency, Certificate of Indigency, Barangay ID, First Time Jobseeker, at Business Clearance.</span>
                    <span class="service-start" aria-hidden="true">Magsimula</span>
                </a>
                <a class="portal-service-card" href="{{ $residentSignedIn ? route('portal.account') : route('portal.login') }}">
                    <strong>Check my requests</strong>
                    <span>Status ng request at kung puwede nang kunin.</span>
                </a>
                <a class="portal-service-card" href="{{ route('portal.information') }}#requirements">
                    <strong>Requirements and fees</strong>
                    <span>Fee ng bawat dokumento at ang dapat dalhin.</span>
                </a>
                <a class="portal-service-card" href="{{ route('portal.information') }}#help">
                    <strong>Get help</strong>
                    <span>Problema sa account o maling detalye sa record.</span>
                </a>
            </div>
        </section>
    </div>
    <p class="portal-band-caption">Barangay Hall ng Anabu I-G, Lungsod ng Imus</p>
</div>
@endsection

@section('content')
<div class="service-info">
    <section class="howto" aria-labelledby="howto-title">
        <h2 id="howto-title">Paano mag-request</h2>
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
        <div class="doc-rates-head">
            <h2 id="doc-rates-title">Mga dokumento at fee</h2>
            <span class="tag-unconfirmed">Hindi pa kumpirmado</span>
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
        <p class="doc-rates-note">Galing sa system ang fees. Hinihintay pa ang kumpirmasyon ng barangay sa fees, requirements, at processing time. <a href="{{ route('portal.information') }}#requirements">Buong requirements at fees</a></p>
    </section>
</div>

<section class="home-section" id="announcements" aria-labelledby="announcements-title">
    <h2 id="announcements-title">Announcements</h2>
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
