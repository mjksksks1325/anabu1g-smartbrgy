<!DOCTYPE html>
<html lang="fil">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Resident Portal') | Barangay Anabu I-G</title>
<link rel="icon" type="image/jpeg" href="{{ asset('images/anabu-logo.jpg') }}">
<link rel="stylesheet" href="{{ asset('css/government.css') }}">
<link rel="stylesheet" href="{{ asset('css/figma-tokens.css') }}?v={{ filemtime(public_path('css/figma-tokens.css')) }}">
<link rel="stylesheet" href="{{ asset('css/portal.css') }}?v={{ filemtime(public_path('css/portal.css')) }}">
<link rel="stylesheet" href="{{ asset('css/figma-portal.css') }}?v={{ filemtime(public_path('css/figma-portal.css')) }}">
</head>
<body{!! $__env->hasSection('hero-header') ? ' class="has-hero-header"' : '' !!}>
<script>try { if (sessionStorage.getItem('smartbrgy_portal_theme') === 'dark') document.body.classList.add('dark-mode'); } catch (_) {}</script>
<a class="skip-link" href="#resident-main">Skip to main content</a>
<div class="gov-bar">
    <div class="gov-bar-inner">
        <span class="gov-identity"><strong>GOVPH</strong><span aria-hidden="true">|</span> Republic of the Philippines</span>
        <div class="gov-actions"><button class="theme-toggle" type="button" data-resident-theme aria-pressed="false" aria-label="Dark mode" title="Dark mode"><svg class="theme-icon theme-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg><svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg></button></div>
    </div>
</div>
<header class="site-header">
    <div class="site-header-inner">
        <a class="site-brand" href="{{ route('home') }}">
            <img src="{{ asset('images/anabu-logo.jpg') }}" width="537" height="465" alt="Opisyal na seal ng Barangay Anabu I-G">
            <span class="site-brand-text"><span class="site-brand-portal">Official website</span><strong>Barangay Anabu I-G</strong><span>City of Imus &middot; Province of Cavite</span></span>
        </a>
        @include('partials.resident-nav')
        <button class="btn btn-outline btn-small menu-toggle" type="button" aria-controls="site-nav" aria-expanded="false" data-menu-toggle>
            <span class="menu-icon" aria-hidden="true"></span>
            <span class="menu-label-closed">Menu</span><span class="menu-label-open">Isara</span>
        </button>
    </div>
</header>
<main id="resident-main" class="site-main" tabindex="-1">
@yield('band')
<div class="container">
@if(session('status'))<p class="alert alert-green" role="status">{{ session('status') }}</p>@endif
@if($errors->any())
<div class="alert alert-red" role="alert"><strong>May kailangang ayusin:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
@yield('content')
</div>
</main>
<footer class="site-footer">
    <div class="site-footer-inner">
        <div class="site-footer-brand">
            <img src="{{ asset('images/anabu-logo.jpg') }}" width="537" height="465" alt="">
            <div>
                <strong>Barangay Anabu I-G</strong>
                <p>Lungsod ng Imus, Cavite</p>
                <p class="site-footer-note">Resident Portal para sa document request at status ng request.</p>
            </div>
        </div>
        <nav class="site-footer-col" aria-labelledby="footer-services-title">
            <h2 id="footer-services-title">Serbisyo</h2>
            <ul>
                <li><a href="{{ route('portal.request.create') }}">Request a document</a></li>
                <li><a href="{{ auth('resident')->check() ? route('portal.account') : route('portal.login') }}">My requests</a></li>
                <li><a href="{{ route('portal.information') }}#requirements">Requirements and fees</a></li>
                <li><a href="{{ route('portal.information') }}#help">Get help</a></li>
            </ul>
        </nav>
        <section class="site-footer-col" aria-labelledby="footer-help-title">
            <h2 id="footer-help-title">Tulong</h2>
            <p>Para sa problema sa account o record, pumunta sa Barangay Hall at dalhin ang valid ID.</p>
            <p><a href="{{ route('home') }}#office">Address at detalye ng Barangay Hall</a></p>
            <p>Emergency: <a href="tel:911">911</a></p>
        </section>
        <nav class="site-footer-col" id="quick-links" aria-labelledby="quick-links-title">
            <h2 id="quick-links-title">Government websites</h2>
            <ul>
                <li><a href="https://cityofimus.gov.ph/" target="_blank" rel="noopener noreferrer">City Government of Imus</a></li>
                <li><a href="https://psa.gov.ph/" target="_blank" rel="noopener noreferrer">Philippine Statistics Authority</a></li>
                <li><a href="https://www.officialgazette.gov.ph/" target="_blank" rel="noopener noreferrer">Official Gazette</a></li>
            </ul>
        </nav>
    </div>
    <div class="site-footer-base">
        <p>Pinangangalagaan ang personal na impormasyon alinsunod sa Republic Act 10173 (Data Privacy Act of 2012).</p>
        <p>Republika ng Pilipinas &middot; Barangay Anabu I-G, Lungsod ng Imus, Cavite</p>
    </div>
</footer>
@hasSection('hero-header')
<script>(() => { const govBar = document.querySelector('.gov-bar'); const update = () => document.body.classList.toggle('hero-header-scrolled', window.scrollY > (govBar ? govBar.offsetHeight : 0) + 24); update(); window.addEventListener('scroll', update, { passive: true }); })();</script>
@endif
@stack('scripts')
<script src="{{ asset('js/resident-account.js') }}?v={{ filemtime(public_path('js/resident-account.js')) }}"></script>
</body>
</html>
