<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') — Barangay Anabu I-G</title>
    <script>try { document.documentElement.dataset.theme = localStorage.getItem('smartbrgy_theme') === 'dark' ? 'dark' : 'light'; document.documentElement.classList.toggle('dark', document.documentElement.dataset.theme === 'dark'); } catch (_) { document.documentElement.dataset.theme = 'light'; }</script>
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
</head>
<body data-personnel-layout="iot" data-admin-localization data-personnel-super-admin="{{ auth()->user()->hasAdministrativeAccess() ? 'true' : 'false' }}">
<script>window.PersonnelNavigation.syncStyles();</script>
<a class="skip-link" href="#main">Skip to main content</a>
<header class="site-header">
    <a wire:navigate.hover class="brand" href="{{ route(App\StaffPermissions::landing(auth()->user())) }}"><img src="{{ asset('images/anabu-logo.jpg') }}" alt="Barangay Anabu I-G seal"><span><strong>Barangay Anabu I-G</strong><small>STAFF WORKSPACE</small></span></a>
    <div class="workspace-title"><span>Staff workspace</span><strong>@yield('title')</strong></div>
    <div class="header-actions">@include('partials.admin-language-toggle')<button class="button quiet staff-theme-icon-button" id="iot-theme-toggle" type="button" aria-label="Dark Mode"><svg class="staff-theme-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg><svg class="staff-theme-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg></button><span class="staff-initials" aria-label="{{ auth()->user()->name }}">{{ auth()->user()->initials() }}</span></div>
</header>
<div class="workspace">
    <nav class="side-nav" aria-label="Staff navigation">
        <div class="side-nav-scroll staff-settings-navigation">
        @if(in_array(auth()->user()->role, ['admin', 'captain', 'staff'], true))
            @if(auth()->user()->hasAnyPermission(['dashboard.view', 'demographics.view']))
            <div class="sidebar-sec">
                <div class="sidebar-label">Overview</div>
                @if(auth()->user()->hasAnyPermission(['dashboard.view']))<a wire:navigate.hover @class(['nav-item', 'current active' => request()->routeIs('admin.dashboard', 'staff.dashboard')]) aria-current="{{ request()->routeIs('admin.dashboard', 'staff.dashboard') ? 'page' : 'false' }}" href="{{ route(auth()->user()->hasAdministrativeAccess() ? 'admin.dashboard' : 'staff.dashboard') }}"><x-staff-icon name="dashboard" />Dashboard</a>@endif
                @if(auth()->user()->hasAnyPermission(['demographics.view']))<a wire:navigate.hover @class(['nav-item', 'current active' => request()->routeIs('staff.demographics')]) aria-current="{{ request()->routeIs('staff.demographics') ? 'page' : 'false' }}" href="{{ route('staff.demographics') }}"><x-staff-icon name="people" />Demographics</a>@endif
            </div>
            @endif
            @if(auth()->user()->hasAnyPermission(['records.view', 'households.view', 'voters.view', 'documents.view', 'eligibility.view', 'incidents.view', 'vawc.view', 'incidents.submit', 'vawc.submit']))
            <div class="sidebar-sec">
                <div class="sidebar-label">Records</div>
                @if(auth()->user()->hasPermission('households.view') && !auth()->user()->hasPermission('records.view'))<a wire:navigate.hover @class(['nav-item', 'current active' => request()->routeIs('staff.households.*')]) aria-current="{{ request()->routeIs('staff.households.*') ? 'page' : 'false' }}" data-screen="records" href="{{ route('staff.households.page') }}"><x-staff-icon name="people" />Households</a>@endif
                @if(auth()->user()->hasAnyPermission(['records.view']))<a @class(['nav-item', 'current active' => request()->routeIs('staff.residents.*', 'staff.resident-applications.*')]) aria-current="{{ request()->routeIs('staff.residents.*', 'staff.resident-applications.*') ? 'page' : 'false' }}" wire:navigate.hover href="{{ route('staff.residents.index') }}"><x-staff-icon name="document" />Resident Records</a>@endif
                @if(auth()->user()->hasAnyPermission(['voters.view']))<a wire:navigate.hover @class(['nav-item', 'current active' => request()->routeIs('staff.voters', 'staff.voter-registrations.*')]) aria-current="{{ request()->routeIs('staff.voters', 'staff.voter-registrations.*') ? 'page' : 'false' }}" href="{{ route('staff.voters') }}"><x-staff-icon name="voters" />Voters</a>@endif
                @if(auth()->user()->hasAnyPermission(['documents.view']))<a wire:navigate.hover @class(['nav-item', 'current active' => request()->routeIs('staff.document-requests.*', 'staff.issued-certificates.*')]) aria-current="{{ request()->routeIs('staff.document-requests.*', 'staff.issued-certificates.*') ? 'page' : 'false' }}" href="{{ route('staff.document-requests.index') }}"><x-staff-icon name="certificate" />Certificates &amp; Clearances</a>@endif
                @if(auth()->user()->hasAnyPermission(['eligibility.view', 'documents.view']))<a wire:navigate.hover @class(['nav-item', 'current active' => request()->routeIs('staff.request-eligibility')]) aria-current="{{ request()->routeIs('staff.request-eligibility') ? 'page' : 'false' }}" href="{{ route('staff.request-eligibility') }}"><x-staff-icon name="clipboard" />Request Eligibility</a>@endif
                @if(auth()->user()->hasAnyPermission(['incidents.view', 'vawc.view', 'incidents.submit', 'vawc.submit']))<a @class(['nav-item', 'current active' => request()->routeIs('staff.incidents.*')]) aria-current="{{ request()->routeIs('staff.incidents.*') ? 'page' : 'false' }}" wire:navigate.hover href="{{ route('staff.incidents.index') }}"><x-staff-icon name="alert" />Incident Reports</a>@endif
            </div>
            @endif
        @endif
            @if(auth()->user()->hasPermission('announcements.view'))
            <div class="sidebar-sec">
                <div class="sidebar-label">Public Information</div>
                <a wire:navigate.hover @class(['nav-item', 'current active' => request()->routeIs('staff.announcements.*')]) aria-current="{{ request()->routeIs('staff.announcements.*') ? 'page' : 'false' }}" href="{{ route('staff.announcements.index') }}"><x-staff-icon name="announcement" />Announcements</a>
            </div>
            @endif
            @if(auth()->user()->hasPermission('rfid.view'))
            <div class="sidebar-sec">
                <div class="sidebar-label">IoT Security</div>
                @if(auth()->user()->hasAnyPermission(['rfid.view']))<a wire:navigate.hover @class(['nav-item', 'current active' => request()->routeIs('staff.rfid-files.*')]) aria-current="{{ request()->routeIs('staff.rfid-files.*') ? 'page' : 'false' }}" href="{{ route('staff.rfid-files.index') }}"><x-staff-icon name="card" />RFID File Tracking</a>@endif
            </div>
            @endif
                @if(auth()->user()->hasAdministrativeAccess())
            <div class="sidebar-sec">
                <div class="sidebar-label">Administration</div>
                <a wire:navigate.hover @class(['nav-item', 'current active' => request()->routeIs('admin.smart-cabinet.*')]) aria-current="{{ request()->routeIs('admin.smart-cabinet.*') ? 'page' : 'false' }}" href="{{ route('admin.smart-cabinet.index') }}"><x-staff-icon name="cabinet" />Smart Cabinet</a>
                <a wire:navigate.hover @class(['nav-item', 'current active' => request()->routeIs('admin.cabinet-access.*')]) aria-current="{{ request()->routeIs('admin.cabinet-access.*') ? 'page' : 'false' }}" href="{{ route('admin.cabinet-access.index') }}"><x-staff-icon name="key" />Employee Cabinet Access</a>
                <a wire:navigate.hover @class(['nav-item', 'current active' => request()->routeIs('admin.audit', 'admin.audit.*')]) aria-current="{{ request()->routeIs('admin.audit', 'admin.audit.*') ? 'page' : 'false' }}" href="{{ route('admin.audit') }}"><x-staff-icon name="audit" />Audit Log</a>
                <a wire:navigate.hover @class(['nav-item', 'current active' => request()->routeIs('admin.users.*')]) aria-current="{{ request()->routeIs('admin.users.*') ? 'page' : 'false' }}" href="{{ route('admin.users.index') }}"><x-staff-icon name="user" />User Management</a>
                <a wire:navigate.hover @class(['nav-item', 'current active' => request()->routeIs('admin.settings')]) aria-current="{{ request()->routeIs('admin.settings') ? 'page' : 'false' }}" href="{{ route('admin.settings') }}"><x-staff-icon name="settings" />Settings</a>
            </div>
        @endif
            <div class="sidebar-sec">
                <a class="nav-item portal-link staff-settings-portal" href="{{ route('home') }}" target="_blank" rel="noopener"><x-staff-icon name="globe" />Resident Portal</a>
            </div>

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
@include('partials.personnel-password-confirmation')
@include('partials.personnel-logout-confirmation')
</body>
</html>
