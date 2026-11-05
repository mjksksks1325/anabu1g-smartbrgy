<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') — Barangay Anabu I-G</title>
    @include('partials.staff-theme-boot')
    <link rel="icon" type="image/jpeg" href="{{ asset('images/anabu-logo.jpg') }}">
    <link rel="stylesheet" href="{{ asset('css/government.css') }}?v={{ filemtime(public_path('css/government.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/figma-tokens.css') }}?v={{ filemtime(public_path('css/figma-tokens.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/admin-iot.css') }}?v={{ filemtime(public_path('css/admin-iot.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/figma-iot.css') }}?v={{ filemtime(public_path('css/figma-iot.css')) }}">
    <link rel="preload" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}" as="style">
    <link rel="preload" href="{{ asset('css/figma-admin.css') }}?v={{ filemtime(public_path('css/figma-admin.css')) }}" as="style">
@include('partials.admin-localization-scripts')
<script src="{{ asset('js/personnel-navigation.js') }}?v={{ filemtime(public_path('js/personnel-navigation.js')) }}" data-navigate-once></script>
<script defer data-navigate-once src="{{ asset('js/rfid-file-tracking.js') }}?v={{ filemtime(public_path('js/rfid-file-tracking.js')) }}"></script>
<script defer data-navigate-once src="{{ asset('js/personnel-password-confirmation.js') }}?v={{ filemtime(public_path('js/personnel-password-confirmation.js')) }}"></script>
<link rel="stylesheet" href="{{ asset('css/password-visibility.css') }}?v={{ filemtime(public_path('css/password-visibility.css')) }}">
<script defer data-navigate-once src="{{ asset('js/password-visibility.js') }}?v={{ filemtime(public_path('js/password-visibility.js')) }}"></script>
<link rel="stylesheet" href="{{ asset('css/form-feedback.css') }}?v={{ filemtime(public_path('css/form-feedback.css')) }}">
<script defer data-navigate-once src="{{ asset('js/form-feedback.js') }}?v={{ filemtime(public_path('js/form-feedback.js')) }}"></script>
<link rel="stylesheet" href="{{ asset('css/staff-theme.css') }}?v={{ filemtime(public_path('css/staff-theme.css')) }}">
{{-- Flux and Tailwind for migrated pages. Linked last so it wins over the legacy sheets; personnel-navigation.js enables it only on pages that opt in with @section('staff-ui'). --}}
@vite('resources/css/staff-ui.css')
</head>
<body data-personnel-layout="iot" {{ $__env->hasSection('staff-ui') ? 'data-staff-ui' : '' }} data-admin-localization data-personnel-super-admin="{{ auth()->user()->hasAdministrativeAccess() ? 'true' : 'false' }}">
<script>window.PersonnelNavigation.syncStyles();</script>
<a class="skip-link" href="#main">Skip to main content</a>
<header class="site-header">
    {{-- Phones: the menu folds away behind this button (personnel-navigation.js); wider screens always show it. --}}
    <button class="iot-menu-button" type="button" aria-controls="staff-side-nav" aria-expanded="false" data-staff-nav-toggle><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>Menu</button>
    <a wire:navigate.hover class="brand" href="{{ route(App\StaffPermissions::landing(auth()->user())) }}"><img src="{{ asset('images/anabu-logo.jpg') }}" alt="Barangay Anabu I-G seal"><span><strong>Barangay Anabu I-G</strong><small>STAFF WORKSPACE</small></span></a>
    <div class="workspace-title"><span>Staff workspace</span><strong>@yield('title')</strong></div>
    <div class="header-actions">@include('partials.admin-language-toggle')<button class="button quiet staff-theme-icon-button" id="iot-theme-toggle" type="button" aria-label="Dark Mode"><svg class="staff-theme-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg><svg class="staff-theme-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg></button><span class="staff-initials" aria-label="{{ auth()->user()->name }}">{{ auth()->user()->initials() }}</span></div>
</header>
<div class="workspace">
    <nav class="side-nav" id="staff-side-nav" aria-label="Staff navigation">
        <div class="side-nav-scroll staff-settings-navigation">
            <x-staff.nav />
        </div>
        <div class="side-nav-user"><x-desktop-user-menu :personnel="true" /></div>
    </nav>
    <main id="main" class="main-content">
        @if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="notice error" role="alert">{{ $errors->first() }}</div>@endif
        @yield('content')
    </main>
</div>
@livewireScripts
@fluxScripts
<script defer data-navigate-once src="{{ asset('js/staff-ui.js') }}?v={{ filemtime(public_path('js/staff-ui.js')) }}"></script>
@include('partials.personnel-password-confirmation')
@include('partials.personnel-logout-confirmation')
</body>
</html>
