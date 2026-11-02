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
<link rel="stylesheet" href="{{ asset('css/portal-v2.css') }}?v={{ filemtime(public_path('css/portal-v2.css')) }}">
<link rel="stylesheet" href="{{ asset('css/portal-resident.css') }}?v={{ filemtime(public_path('css/portal-resident.css')) }}">
<script>window.PORTAL_I18N = {{ Illuminate\Support\Js::from(['locale' => app()->getLocale(), 'url' => route('portal.locale'), 'csrfUrl' => route('portal.csrf-token')]) }};</script>
<script src="{{ route('portal.translations') }}"></script>
<script src="{{ asset('js/portal-localization.js') }}?v={{ filemtime(public_path('js/portal-localization.js')) }}"></script>
<link rel="stylesheet" href="{{ asset('css/password-visibility.css') }}?v={{ filemtime(public_path('css/password-visibility.css')) }}">
<script defer data-navigate-once src="{{ asset('js/password-visibility.js') }}?v={{ filemtime(public_path('js/password-visibility.js')) }}"></script>
<link rel="stylesheet" href="{{ asset('css/form-feedback.css') }}?v={{ filemtime(public_path('css/form-feedback.css')) }}">
<script defer data-navigate-once src="{{ asset('js/form-feedback.js') }}?v={{ filemtime(public_path('js/form-feedback.js')) }}"></script>
</head>
<body{!! $__env->hasSection('hero-header') ? ' class="has-hero-header"' : '' !!}>
<script>try { if (sessionStorage.getItem('smartbrgy_portal_theme') === 'dark') document.body.classList.add('dark-mode'); } catch (_) {}</script>
<a class="skip-link" href="#resident-main"><span data-portal-i18n="Skip to main content">{{ __('Skip to main content') }}</span></a>
<div class="gov-bar">
    <div class="gov-bar-inner">
        <span class="gov-identity"><strong><span data-portal-i18n="GOVPH">{{ __('GOVPH') }}</span></strong><span aria-hidden="true">|</span> <span data-portal-i18n="Republic of the Philippines">{{ __('Republic of the Philippines') }}</span></span>
        <div class="gov-actions"><button class="theme-toggle" type="button" data-resident-theme aria-pressed="false" aria-label="{{ __('Dark mode') }}" data-portal-i18n-aria-label="Dark mode" title="{{ __('Dark mode') }}" data-portal-i18n-title="Dark mode"><svg class="theme-icon theme-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg><svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg></button></div>
    </div>
</div>
<header class="site-header">
    <div class="site-header-inner">
        <a class="site-brand" href="{{ route('home') }}">
            <img src="{{ asset('images/anabu-logo.jpg') }}" width="537" height="465" alt="{{ __('Opisyal na seal ng Barangay Anabu I-G') }}" data-portal-i18n-alt="Opisyal na seal ng Barangay Anabu I-G">
            <span class="site-brand-text"><strong><span data-portal-i18n="Barangay Anabu I-G">{{ __('Barangay Anabu I-G') }}</span></strong><span><span data-portal-i18n="Lungsod ng Imus, Cavite">{{ __('Lungsod ng Imus, Cavite') }}</span></span></span>
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
@if(session('status'))<p class="alert alert-green rp-flash" role="status"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.6 2.6L16 9.5"/></svg><span data-portal-message>{{ __(session('status')) }}</span></p>@endif
@if($errors->any())
<div class="alert alert-red rp-error-summary" role="alert" tabindex="-1" data-error-summary>
    <h2><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 16.5h.01"/></svg><span data-portal-i18n="May kailangang ayusin:">{{ __('May kailangang ayusin:') }}</span></h2>
    <ul>@foreach($errors->getMessages() as $field => $messages)@foreach($messages as $error)<li><a href="#resident-main" data-error-field="{{ $field }}" data-portal-message>{{ $error }}</a></li>@endforeach @endforeach</ul>
</div>
@endif
@yield('content')
</div>
</main>
<footer class="site-footer">
    <div class="site-footer-inner">
        <section class="site-footer-col" aria-labelledby="footer-hall-title">
            <h2 id="footer-hall-title"><span data-portal-i18n="Barangay Anabu I-G Hall">{{ __('Barangay Anabu I-G Hall') }}</span></h2>
            <p><span data-portal-i18n="Lungsod ng Imus, Cavite">{{ __('Lungsod ng Imus, Cavite') }}</span></p>
            <p><span data-portal-i18n="Office hours:">{{ __('Office hours:') }}</span> <span class="tag-unconfirmed"><span data-portal-i18n="Hindi pa kumpirmado">{{ __('Hindi pa kumpirmado') }}</span></span></p>
            <p><span data-portal-i18n="Emergency:">{{ __('Emergency:') }}</span> <a href="tel:911">911</a></p>
        </section>
        <nav class="site-footer-col" aria-labelledby="footer-more-title">
            <h2 id="footer-more-title"><span data-portal-i18n="Iba pa">{{ __('Iba pa') }}</span></h2>
            <ul>
                <li><a href="{{ route('portal.request.create') }}"><span data-portal-i18n="Request a document">{{ __('Request a document') }}</span></a></li>
                <li><a href="{{ route('portal.information') }}#requirements"><span data-portal-i18n="Requirements and fees">{{ __('Requirements and fees') }}</span></a></li>
                <li><a href="{{ route('portal.officials') }}" @if(request()->routeIs('portal.officials')) aria-current="page" @endif><span data-portal-i18n="Barangay officials">{{ __('Barangay officials') }}</span></a></li>
                <li><a href="{{ route('portal.announcements.index') }}"><span data-portal-i18n="Announcements">{{ __('Announcements') }}</span></a></li>
                <li><a href="{{ route('portal.information') }}#help"><span data-portal-i18n="Get help">{{ __('Get help') }}</span></a></li>
                <li><a href="{{ route('portal.information') }}#contacts"><span data-portal-i18n="Emergency hotlines">{{ __('Emergency hotlines') }}</span></a></li>
                <li><a href="{{ route('portal.information') }}#office"><span data-portal-i18n="Address at detalye ng Barangay Hall">{{ __('Address at detalye ng Barangay Hall') }}</span></a></li>
            </ul>
        </nav>
        <nav class="site-footer-col" id="quick-links" aria-labelledby="quick-links-title">
            <h2 id="quick-links-title"><span data-portal-i18n="Government websites">{{ __('Government websites') }}</span></h2>
            <ul>
                <li><a href="https://cityofimus.gov.ph/" target="_blank" rel="noopener noreferrer"><span data-portal-i18n="City Government of Imus">{{ __('City Government of Imus') }}</span></a></li>
                <li><a href="https://cavite.gov.ph/" target="_blank" rel="noopener noreferrer"><span data-portal-i18n="Provincial Government of Cavite">{{ __('Provincial Government of Cavite') }}</span></a></li>
                <li><a href="https://psa.gov.ph/" target="_blank" rel="noopener noreferrer"><span data-portal-i18n="Philippine Statistics Authority">{{ __('Philippine Statistics Authority') }}</span></a></li>
                <li><a href="https://www.officialgazette.gov.ph/" target="_blank" rel="noopener noreferrer"><span data-portal-i18n="Official Gazette">{{ __('Official Gazette') }}</span></a></li>
            </ul>
        </nav>
        <section class="site-footer-col" aria-labelledby="footer-portal-title">
            <h2 id="footer-portal-title"><span data-portal-i18n="SmartBrgy Online Portal">{{ __('SmartBrgy Online Portal') }}</span></h2>
            <p><span data-portal-i18n="Pinangangalagaan ang personal na impormasyon alinsunod sa Republic Act 10173 (Data Privacy Act of 2012).">{{ __('Pinangangalagaan ang personal na impormasyon alinsunod sa Republic Act 10173 (Data Privacy Act of 2012).') }}</span></p>
            <p class="site-footer-note"><span data-portal-i18n="Republika ng Pilipinas · Barangay Anabu I-G, Lungsod ng Imus, Cavite">{{ __('Republika ng Pilipinas · Barangay Anabu I-G, Lungsod ng Imus, Cavite') }}</span></p>
        </section>
    </div>
</footer>
@hasSection('hero-header')
<script>(() => { const govBar = document.querySelector('.gov-bar'); const update = () => document.body.classList.toggle('hero-header-scrolled', window.scrollY > (govBar ? govBar.offsetHeight : 0) + 24); update(); window.addEventListener('scroll', update, { passive: true }); })();</script>
@endif
@stack('scripts')
<script src="{{ asset('js/resident-account.js') }}?v={{ filemtime(public_path('js/resident-account.js')) }}"></script>
<script src="{{ asset('js/resident-photo-input.js') }}?v={{ filemtime(public_path('js/resident-photo-input.js')) }}"></script>
<script src="{{ asset('js/portal-errors.js') }}?v={{ filemtime(public_path('js/portal-errors.js')) }}"></script>
<script src="{{ asset('js/photo-preview.js') }}?v={{ filemtime(public_path('js/photo-preview.js')) }}"></script>
</body>
</html>
