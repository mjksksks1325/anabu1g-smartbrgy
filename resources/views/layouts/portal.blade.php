<!DOCTYPE html>
<html lang="fil">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Resident Portal') | Barangay Anabu I-G</title>
<link rel="icon" type="image/jpeg" href="{{ asset('images/anabu-logo.jpg') }}">
<link rel="stylesheet" href="{{ asset('css/government.css') }}">
<link rel="stylesheet" href="{{ asset('css/portal.css') }}?v={{ filemtime(public_path('css/portal.css')) }}">
</head>
<body>
<a class="skip-link" href="#resident-main">Skip to main content</a>
<div class="gov-bar">
    <div class="gov-bar-inner">
        <span>Republika ng Pilipinas &middot; Lungsod ng Imus, Cavite</span>
        <button class="theme-toggle" type="button" data-resident-theme aria-pressed="false">Dark mode</button>
    </div>
</div>
<header class="site-header">
    <div class="site-header-inner">
        <a class="site-brand" href="{{ route('home') }}">
            <img src="{{ asset('images/anabu-logo.jpg') }}" width="537" height="465" alt="Opisyal na seal ng Barangay Anabu I-G">
            <span class="site-brand-text"><strong>Barangay Anabu I-G</strong><span>Lungsod ng Imus, Cavite</span><span class="site-brand-portal">Resident Portal</span></span>
        </a>
        <button class="btn btn-outline btn-small menu-toggle" type="button" aria-controls="site-nav" aria-expanded="false" data-menu-toggle>
            <span class="menu-icon" aria-hidden="true"></span>
            <span class="menu-label-closed">Menu</span><span class="menu-label-open">Isara</span>
        </button>
    </div>
</header>
@include('partials.resident-nav')
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
@stack('scripts')
<script src="{{ asset('js/resident-account.js') }}?v={{ filemtime(public_path('js/resident-account.js')) }}"></script>
</body>
</html>
