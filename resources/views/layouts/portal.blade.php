<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', __('Resident Portal')) | Barangay Anabu I-G</title>
<link rel="icon" type="image/jpeg" href="{{ asset('images/anabu-logo.jpg') }}">
<link rel="stylesheet" href="{{ asset('css/government.css') }}">
<link rel="stylesheet" href="{{ asset('css/figma-tokens.css') }}?v={{ filemtime(public_path('css/figma-tokens.css')) }}">
<link rel="stylesheet" href="{{ asset('css/portal.css') }}?v={{ filemtime(public_path('css/portal.css')) }}">
<link rel="stylesheet" href="{{ asset('css/figma-portal.css') }}?v={{ filemtime(public_path('css/figma-portal.css')) }}">
<link rel="stylesheet" href="{{ asset('css/portal-localization.css') }}?v={{ filemtime(public_path('css/portal-localization.css')) }}">
<script>window.PORTAL_I18N = {{ Illuminate\Support\Js::from(['locale' => app()->getLocale(), 'url' => route('portal.locale'), 'csrfUrl' => route('portal.csrf-token')]) }};</script>
<script src="{{ route('portal.translations') }}"></script>
<script src="{{ asset('js/portal-localization.js') }}?v={{ filemtime(public_path('js/portal-localization.js')) }}"></script>
</head>
<body{!! $__env->hasSection('hero-header') ? ' class="has-hero-header"' : '' !!}>
<script>try { if (sessionStorage.getItem('smartbrgy_portal_theme') === 'dark') document.body.classList.add('dark-mode'); } catch (_) {}</script>
<a class="skip-link" href="#resident-main"><span data-portal-i18n="Skip to main content">{{ __('Skip to main content') }}</span></a>
<div class="gov-bar">
    <div class="gov-bar-inner">
        <span class="gov-identity"><strong><span data-portal-i18n="GOVPH">{{ __('GOVPH') }}</span></strong><span aria-hidden="true">|</span> <span data-portal-i18n="Republic of the Philippines">{{ __('Republic of the Philippines') }}</span></span>
        <div class="gov-actions">@include('partials.portal-language-toggle')<button class="theme-toggle" type="button" data-resident-theme aria-pressed="false" aria-label="{{ __('Dark mode') }}" data-portal-i18n-aria-label="Dark mode" title="{{ __('Dark mode') }}" data-portal-i18n-title="Dark mode"><svg class="theme-icon theme-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg><svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg></button></div>
    </div>
</div>
<header class="site-header">
    <div class="site-header-inner">
        <a class="site-brand" href="{{ route('home') }}">
            <img src="{{ asset('images/anabu-logo.jpg') }}" width="537" height="465" alt="{{ __('Opisyal na seal ng Barangay Anabu I-G') }}" data-portal-i18n-alt="Opisyal na seal ng Barangay Anabu I-G">
            <span class="site-brand-text"><span class="site-brand-portal"><span data-portal-i18n="Official website">{{ __('Official website') }}</span></span><strong><span data-portal-i18n="Barangay Anabu I-G">{{ __('Barangay Anabu I-G') }}</span></strong><span><span data-portal-i18n="City of Imus · Province of Cavite">{{ __('City of Imus · Province of Cavite') }}</span></span></span>
        </a>
        @include('partials.resident-nav')
        <button class="btn btn-outline btn-small menu-toggle" type="button" aria-controls="site-nav" aria-expanded="false" data-menu-toggle>
            <span class="menu-icon" aria-hidden="true"></span>
            <span class="menu-label-closed"><span data-portal-i18n="Menu">{{ __('Menu') }}</span></span><span class="menu-label-open"><span data-portal-i18n="Isara">{{ __('Isara') }}</span></span>
        </button>
    </div>
</header>
<main id="resident-main" class="site-main" tabindex="-1">
@yield('band')
<div class="container">
@if(session('status'))<p class="alert alert-green" role="status"><span data-portal-message>{{ __(session('status')) }}</span></p>@endif
@if($errors->any())
<div class="alert alert-red" role="alert"><strong><span data-portal-i18n="May kailangang ayusin:">{{ __('May kailangang ayusin:') }}</span></strong><ul>@foreach($errors->all() as $error)<li data-portal-message>{{ $error }}</li>@endforeach</ul></div>
@endif
@yield('content')
</div>
</main>
<footer class="site-footer">
    <div class="site-footer-inner">
        <div class="site-footer-brand">
            <img src="{{ asset('images/anabu-logo.jpg') }}" width="537" height="465" alt="">
            <div>
                <strong><span data-portal-i18n="Barangay Anabu I-G">{{ __('Barangay Anabu I-G') }}</span></strong>
                <p><span data-portal-i18n="Lungsod ng Imus, Cavite">{{ __('Lungsod ng Imus, Cavite') }}</span></p>
                <p class="site-footer-note"><span data-portal-i18n="Resident Portal para sa document request at status ng request.">{{ __('Resident Portal para sa document request at status ng request.') }}</span></p>
            </div>
        </div>
        <nav class="site-footer-col" aria-labelledby="footer-services-title">
            <h2 id="footer-services-title"><span data-portal-i18n="Serbisyo">{{ __('Serbisyo') }}</span></h2>
            <ul>
                <li><a href="{{ route('portal.request.create') }}"><span data-portal-i18n="Request a document">{{ __('Request a document') }}</span></a></li>
                <li><a href="{{ auth('resident')->check() ? route('portal.account') : route('portal.login') }}"><span data-portal-i18n="My requests">{{ __('My requests') }}</span></a></li>
                <li><a href="{{ route('portal.information') }}#requirements"><span data-portal-i18n="Requirements and fees">{{ __('Requirements and fees') }}</span></a></li>
                <li><a href="{{ route('portal.information') }}#help"><span data-portal-i18n="Get help">{{ __('Get help') }}</span></a></li>
            </ul>
        </nav>
        <section class="site-footer-col" aria-labelledby="footer-help-title">
            <h2 id="footer-help-title"><span data-portal-i18n="Tulong">{{ __('Tulong') }}</span></h2>
            <p><span data-portal-i18n="Para sa problema sa account o record, pumunta sa Barangay Hall at dalhin ang valid ID.">{{ __('Para sa problema sa account o record, pumunta sa Barangay Hall at dalhin ang valid ID.') }}</span></p>
            <p><a href="{{ route('home') }}#office"><span data-portal-i18n="Address at detalye ng Barangay Hall">{{ __('Address at detalye ng Barangay Hall') }}</span></a></p>
            <p><span data-portal-i18n="Emergency:">{{ __('Emergency:') }}</span> <a href="tel:911">911</a></p>
        </section>
        <nav class="site-footer-col" id="quick-links" aria-labelledby="quick-links-title">
            <h2 id="quick-links-title"><span data-portal-i18n="Government websites">{{ __('Government websites') }}</span></h2>
            <ul>
                <li><a href="https://cityofimus.gov.ph/" target="_blank" rel="noopener noreferrer"><span data-portal-i18n="City Government of Imus">{{ __('City Government of Imus') }}</span></a></li>
                <li><a href="https://cavite.gov.ph/" target="_blank" rel="noopener noreferrer"><span data-portal-i18n="Provincial Government of Cavite">{{ __('Provincial Government of Cavite') }}</span></a></li>
                <li><a href="https://psa.gov.ph/" target="_blank" rel="noopener noreferrer"><span data-portal-i18n="Philippine Statistics Authority">{{ __('Philippine Statistics Authority') }}</span></a></li>
                <li><a href="https://www.officialgazette.gov.ph/" target="_blank" rel="noopener noreferrer"><span data-portal-i18n="Official Gazette">{{ __('Official Gazette') }}</span></a></li>
            </ul>
        </nav>
    </div>
    <div class="site-footer-base">
        <p><span data-portal-i18n="Pinangangalagaan ang personal na impormasyon alinsunod sa Republic Act 10173 (Data Privacy Act of 2012).">{{ __('Pinangangalagaan ang personal na impormasyon alinsunod sa Republic Act 10173 (Data Privacy Act of 2012).') }}</span></p>
        <p><span data-portal-i18n="Republika ng Pilipinas · Barangay Anabu I-G, Lungsod ng Imus, Cavite">{{ __('Republika ng Pilipinas · Barangay Anabu I-G, Lungsod ng Imus, Cavite') }}</span></p>
    </div>
</footer>
@hasSection('hero-header')
<script>(() => { const govBar = document.querySelector('.gov-bar'); const update = () => document.body.classList.toggle('hero-header-scrolled', window.scrollY > (govBar ? govBar.offsetHeight : 0) + 24); update(); window.addEventListener('scroll', update, { passive: true }); })();</script>
@endif
@stack('scripts')
<script src="{{ asset('js/resident-account.js') }}?v={{ filemtime(public_path('js/resident-account.js')) }}"></script>
</body>
</html>
