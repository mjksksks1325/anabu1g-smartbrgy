@extends('layouts.portal')
@section('title', __('Tulong'))
@section('band')
<x-portal.page-band title="Tulong">
    <p><span data-portal-i18n="Mga dokumentong puwedeng i-request online, ang fee na nasa system, at kung saan hihingi ng tulong. Puwede itong basahin kahit walang account.">{{ __('Mga dokumentong puwedeng i-request online, ang fee na nasa system, at kung saan hihingi ng tulong. Puwede itong basahin kahit walang account.') }}</span></p>
    <x-slot:below>
        <nav class="jump-links rp-topics" data-portal-i18n-aria-label="Sa page na ito" aria-label="{{ __('Sa page na ito') }}">
            <a href="#requirements"><span class="rp-topic-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/></svg></span><span data-portal-i18n="Mga dokumento">{{ __('Mga dokumento') }}</span></a>
            <a href="#faq"><span class="rp-topic-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 0 1 5 0c0 2-2.5 2-2.5 4M12 17h.01"/></svg></span><span data-portal-i18n="Mga madalas itanong">{{ __('Mga madalas itanong') }}</span></a>
            <a href="#help"><span class="rp-topic-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span><span data-portal-i18n="Problema sa account o record">{{ __('Problema sa account o record') }}</span></a>
            <a href="#contacts"><span class="rp-topic-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg></span><span data-portal-i18n="Emergency hotlines">{{ __('Emergency hotlines') }}</span></a>
            <a href="#office"><span class="rp-topic-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V10l7-5 7 5v11M9 21v-6h6v6"/></svg></span><span data-portal-i18n="Barangay Hall">{{ __('Barangay Hall') }}</span></a>
            <a href="{{ route('portal.officials') }}"><span class="rp-topic-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M6 16a3 3 0 0 1 6 0M14 10h4M14 14h4"/></svg></span><span data-portal-i18n="Barangay officials">{{ __('Barangay officials') }}</span></a>
        </nav>
    </x-slot:below>
</x-portal.page-band>
@endsection
@section('content')
<section class="section section-first" id="requirements" aria-labelledby="requirements-title">
    <div class="section-head"><h2 id="requirements-title"><span data-portal-i18n="Mga dokumento">{{ __('Mga dokumento') }}</span></h2></div>
    <div class="requirements-intro">
        <div class="v2-callout">
            <svg class="v2-callout-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5h.01"/></svg>
            <div>
            <h3><span data-portal-i18n="Para sa lahat ng dokumento">{{ __('Para sa lahat ng dokumento') }}</span></h3>
            <ul class="plain-list">
                <li><span data-portal-i18n="Kailangan ng resident account para mag-request online.">{{ __('Kailangan ng resident account para mag-request online.') }}</span></li>
                <li><span data-portal-i18n="Nire-review ng barangay staff ang bawat request. May mga dokumentong posibleng mangailangan ng dagdag na verification bago i-release.">{{ __('Nire-review ng barangay staff ang bawat request. May mga dokumentong posibleng mangailangan ng dagdag na verification bago i-release.') }}</span></li>
                <li><span data-portal-i18n="Sa Barangay Hall kukunin ang dokumento. Dalhin ang valid ID at ang reference number.">{{ __('Sa Barangay Hall kukunin ang dokumento. Dalhin ang valid ID at ang reference number.') }}</span></li>
                <li><span data-portal-i18n="Sa Barangay Hall babayaran ang fee. Valid ang online request nang 30 araw mula sa pag-submit.">{{ __('Sa Barangay Hall babayaran ang fee. Valid ang online request nang 30 araw mula sa pag-submit.') }}</span></li>
            </ul>
            </div>
        </div>
        <p class="v2-callout v2-callout-warning"><strong><span data-portal-i18n="Hindi pa kumpirmado:">{{ __('Hindi pa kumpirmado:') }}</span></strong> <span data-portal-i18n="Business Clearance fee, eksaktong requirements, at processing time. Regular Barangay ID ang fee na nakalista. Magtanong sa Barangay Hall bago kumuha.">{{ __('Business Clearance fee, eksaktong requirements, at processing time. Regular Barangay ID ang fee na nakalista. Magtanong sa Barangay Hall bago kumuha.') }}</span></p>
    </div>
    <div class="service-table">
    <div class="service-list-head" aria-hidden="true"><span><span data-portal-i18n="Dokumento">{{ __('Dokumento') }}</span></span><span><span data-portal-i18n="Fee">{{ __('Fee') }}</span></span><span></span></div>
    <ul class="service-list">
        @foreach($services as $service)
            <li class="service-item">
                <h3 id="service-document-{{ $loop->index }}"><span data-portal-i18n="{{ $service->value }}">{{ __($service->value) }}</span></h3>
                <p class="service-fee"><span class="service-fee-label"><span data-portal-i18n="Fee:">{{ __('Fee:') }}</span> </span><span data-portal-i18n="{{ $service->feeLabel() }}">{{ __($service->feeLabel()) }}</span>@if(! $service->feeConfirmed()) <span class="tag-unconfirmed"><span data-portal-i18n="To be confirmed">{{ __('To be confirmed') }}</span></span>@endif</p>
                <p class="service-validity">@if($service->validityPeriodInMonths() === 12)<span data-portal-i18n="Valid nang 1 taon mula pagka-issue">{{ __('Valid nang 1 taon mula pagka-issue') }}</span>@else<span data-portal-i18n="Valid nang :months buwan mula pagka-issue" data-portal-params="{{ json_encode(['months' => $service->validityPeriodInMonths()]) }}">{{ __('Valid nang :months buwan mula pagka-issue', ['months' => $service->validityPeriodInMonths()]) }}</span>@endif</p>
                <a class="btn btn-outline btn-small" href="{{ route('portal.request.create', ['service' => $service->portalCode()]) }}" aria-labelledby="service-request-{{ $loop->index }} service-document-{{ $loop->index }}"><span id="service-request-{{ $loop->index }}" data-portal-i18n="Request">{{ __('Request') }}</span></a>
            </li>
        @endforeach
    </ul>
    </div>
</section>

<section class="section" id="faq" aria-labelledby="faq-title">
    <div class="section-head"><h2 id="faq-title"><span data-portal-i18n="Mga madalas itanong">{{ __('Mga madalas itanong') }}</span></h2></div>
    <div class="rp-faq">
        <details>
            <summary><span data-portal-i18n="Saan at kailan babayaran ang fee?">{{ __('Saan at kailan babayaran ang fee?') }}</span></summary>
            <p><span data-portal-i18n="Sa Barangay Hall, kapag kinuha ninyo ang dokumento. Walang online payment.">{{ __('Sa Barangay Hall, kapag kinuha ninyo ang dokumento. Walang online payment.') }}</span></p>
        </details>
        <details>
            <summary><span data-portal-i18n="Ano ang dadalhin pagkuha ng dokumento?">{{ __('Ano ang dadalhin pagkuha ng dokumento?') }}</span></summary>
            <p><span data-portal-i18n="Ang original na valid ID at ang reference number ng request.">{{ __('Ang original na valid ID at ang reference number ng request.') }}</span></p>
        </details>
        <details>
            <summary><span data-portal-i18n="Paano ko malalaman kung handa na ang dokumento?">{{ __('Paano ko malalaman kung handa na ang dokumento?') }}</span></summary>
            <p><span data-portal-i18n="Buksan ang My requests. Kapag Ready for release na ang status, puwede na itong kunin sa Barangay Hall.">{{ __('Buksan ang My requests. Kapag Ready for release na ang status, puwede na itong kunin sa Barangay Hall.') }}</span></p>
        </details>
        <details>
            <summary><span data-portal-i18n="Gaano katagal valid ang certificate?">{{ __('Gaano katagal valid ang certificate?') }}</span></summary>
            <p><span data-portal-i18n="Valid nang 3 buwan mula pagka-issue. Ang Barangay ID ay valid nang 1 taon.">{{ __('Valid nang 3 buwan mula pagka-issue. Ang Barangay ID ay valid nang 1 taon.') }}</span></p>
        </details>
        <details>
            <summary><span data-portal-i18n="Bakit hindi naaprubahan ang request ko?">{{ __('Bakit hindi naaprubahan ang request ko?') }}</span></summary>
            <p><span data-portal-i18n="Buksan ang request sa My requests para makita ang dahilan. Puwedeng mag-submit ng bagong request kapag naayos na ang problema.">{{ __('Buksan ang request sa My requests para makita ang dahilan. Puwedeng mag-submit ng bagong request kapag naayos na ang problema.') }}</span></p>
        </details>
        <details>
            <summary><span data-portal-i18n="Nakalimutan ko ang password ko.">{{ __('Nakalimutan ko ang password ko.') }}</span></summary>
            <p><span data-portal-i18n="Pindutin ang &quot;Nakalimutan ang password?&quot; sa Log in page. Padadalhan kayo ng link sa email ng inyong account.">{{ __('Pindutin ang "Nakalimutan ang password?" sa Log in page. Padadalhan kayo ng link sa email ng inyong account.') }}</span></p>
        </details>
        <details>
            <summary><span data-portal-i18n="May mali sa pangalan o address ko. Paano ito aayusin?">{{ __('May mali sa pangalan o address ko. Paano ito aayusin?') }}</span></summary>
            <p><span data-portal-i18n="Hindi ito mae-edit online. Pumunta sa Barangay Hall at dalhin ang valid ID para magpa-correct.">{{ __('Hindi ito mae-edit online. Pumunta sa Barangay Hall at dalhin ang valid ID para magpa-correct.') }}</span></p>
        </details>
    </div>
</section>

<section class="section" id="help" aria-labelledby="help-title">
    <div class="section-head"><h2 id="help-title"><span data-portal-i18n="Get help">{{ __('Get help') }}</span></h2></div>
    <div class="two-column">
        <div class="v2-card">
            <h3><span data-portal-i18n="Problema sa account o record">{{ __('Problema sa account o record') }}</span></h3>
            <p><span data-portal-i18n="Pumunta sa Barangay Hall at dalhin ang valid ID. Matutulungan kayo ng staff sa:">{{ __('Pumunta sa Barangay Hall at dalhin ang valid ID. Matutulungan kayo ng staff sa:') }}</span></p>
            <ul class="plain-list help-list">
                <li><span data-portal-i18n="record na hindi mahanap sa">{{ __('record na hindi mahanap sa') }}</span> <a href="{{ route('portal.register') }}"><span data-portal-i18n="Create account">{{ __('Create account') }}</span></a></li>
                <li><span data-portal-i18n="maling pangalan, address, o contact number">{{ __('maling pangalan, address, o contact number') }}</span></li>
                <li><span data-portal-i18n="activation code kung hindi dumating ang email">{{ __('activation code kung hindi dumating ang email') }}</span></li>
                <li><span data-portal-i18n="account na hindi magamit ang online services">{{ __('account na hindi magamit ang online services') }}</span></li>
            </ul>
            <p class="next-note"><span data-portal-i18n="Nakalimutan ang password?">{{ __('Nakalimutan ang password?') }}</span> <a href="{{ route('password.request', ['from' => 'portal']) }}"><span data-portal-i18n="I-reset ang password">{{ __('I-reset ang password') }}</span></a>.</p>
        </div>
        <div class="v2-card v2-card-info">
            <h3><span data-portal-i18n="Barangay office">{{ __('Barangay office') }}</span></h3>
            <dl class="office-list">
                <dt><span data-portal-i18n="Address">{{ __('Address') }}</span></dt><dd><span data-portal-i18n="Barangay Anabu I-G, Lungsod ng Imus, Cavite">{{ __('Barangay Anabu I-G, Lungsod ng Imus, Cavite') }}</span></dd>
                <dt><span data-portal-i18n="Telepono">{{ __('Telepono') }}</span></dt><dd><span class="tag-unconfirmed"><span data-portal-i18n="Hindi pa kumpirmado">{{ __('Hindi pa kumpirmado') }}</span></span></dd>
                <dt><span data-portal-i18n="Office hours">{{ __('Office hours') }}</span></dt><dd><span class="tag-unconfirmed"><span data-portal-i18n="Hindi pa kumpirmado">{{ __('Hindi pa kumpirmado') }}</span></span></dd>
            </dl>
            <p class="next-note"><span data-portal-i18n="Para sa emergency, tingnan ang">{{ __('Para sa emergency, tingnan ang') }}</span> <a href="#contacts"><span data-portal-i18n="emergency hotlines">{{ __('emergency hotlines') }}</span></a>.</p>
        </div>
    </div>
</section>

<section class="section" id="contacts" aria-labelledby="contacts-title">
    <div class="section-head">
        <h2 id="contacts-title"><span data-portal-i18n="Emergency hotlines">{{ __('Emergency hotlines') }}</span></h2>
        <p><span data-portal-i18n="Mula sa">{{ __('Mula sa') }}</span> <a href="https://cityofimus.gov.ph/" target="_blank" rel="noopener noreferrer"><span data-portal-i18n="City of Imus website">{{ __('City of Imus website') }}</span></a></p>
    </div>
    <ul class="v2-hotlines">
        <li><span><span data-portal-i18n="National emergency hotline">{{ __('National emergency hotline') }}</span></span><a href="tel:911">911</a></li>
        <li><span><span data-portal-i18n="City of Imus emergency">{{ __('City of Imus emergency') }}</span></span><a href="tel:+63468889911">(046) 888 9911</a></li>
        <li><span><span data-portal-i18n="Imus PNP">{{ __('Imus PNP') }}</span></span><a href="tel:+639985985601">0998 598 5601</a></li>
        <li><span><span data-portal-i18n="Bureau of Fire Protection">{{ __('Bureau of Fire Protection') }}</span></span><a href="tel:+639155283256">0915 528 3256</a></li>
    </ul>
</section>

<section class="section office-section v2-office" id="office" aria-labelledby="office-title">
    <div class="office-details v2-card">
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
    <div class="office-map v2-card" id="location">
        <div class="map-panel">
            <iframe data-portal-i18n-title="Mapa ng Barangay Anabu I-G, Imus, Cavite" title="{{ __('Mapa ng Barangay Anabu I-G, Imus, Cavite') }}" src="https://maps.google.com/maps?q=Barangay%20Anabu%20I-G%2C%20Imus%2C%20Cavite&amp;output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            <p><span data-portal-i18n="Area ng Barangay Anabu I-G.">{{ __('Area ng Barangay Anabu I-G.') }}</span> <span class="tag-unconfirmed"><span data-portal-i18n="Hindi pa kumpirmado">{{ __('Hindi pa kumpirmado') }}</span></span> <span data-portal-i18n="ang eksaktong entrance ng Barangay Hall.">{{ __('ang eksaktong entrance ng Barangay Hall.') }}</span> <a href="https://www.google.com/maps/search/?api=1&amp;query=Barangay+Anabu+I-G%2C+Imus%2C+Cavite" target="_blank" rel="noopener noreferrer"><span data-portal-i18n="Buksan sa Google Maps">{{ __('Buksan sa Google Maps') }}</span></a></p>
        </div>
    </div>
</section>

<section class="section" aria-labelledby="resources-title">
    <div class="section-head"><h2 id="resources-title"><span data-portal-i18n="Official resources and assistance">{{ __('Official resources and assistance') }}</span></h2></div>
    <div class="two-column">
        @foreach($resources as $resource)
            <article class="v2-card">
                <h3><span data-portal-i18n="{{ $resource['title'] }}">{{ __($resource['title']) }}</span></h3>
                <p><span data-portal-i18n="{{ $resource['description'] }}">{{ __($resource['description']) }}</span></p>
                <p class="next-note"><a class="btn btn-outline btn-small" href="{{ $resource['url'] }}" target="_blank" rel="noopener noreferrer"><span data-portal-i18n="{{ $resource['link_label'] }}">{{ __($resource['link_label']) }}</span> <span class="sr-only"><span data-portal-i18n="(opens in a new tab)">{{ __('(opens in a new tab)') }}</span></span></a></p>
            </article>
        @endforeach
    </div>
</section>
@endsection
