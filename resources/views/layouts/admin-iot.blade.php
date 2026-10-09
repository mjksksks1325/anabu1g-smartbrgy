<!DOCTYPE html>
<html lang="en">
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
<script src="{{ asset('js/personnel-navigation.js') }}?v={{ filemtime(public_path('js/personnel-navigation.js')) }}" data-navigate-once></script>
<script defer data-navigate-once src="{{ asset('js/personnel-password-confirmation.js') }}?v={{ filemtime(public_path('js/personnel-password-confirmation.js')) }}"></script>
</head>
<body data-personnel-layout="iot" data-personnel-super-admin="{{ auth()->user()->isSuperAdmin() ? 'true' : 'false' }}">
<script>window.PersonnelNavigation.syncStyles();</script>
<a class="skip-link" href="#main">Skip to main content</a>
<header class="site-header">
    <a wire:navigate class="brand" href="{{ route(App\StaffPermissions::landing(auth()->user())) }}"><img src="{{ asset('images/anabu-logo.jpg') }}" alt="Barangay Anabu I-G seal"><span><strong>Barangay Anabu I-G</strong><small>STAFF WORKSPACE</small></span></a>
    <div class="workspace-title"><span>Staff workspace</span><strong>@yield('title')</strong></div>
    <div class="header-actions"><button class="button quiet" id="iot-theme-toggle" type="button">Dark Mode</button>@if(auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('rfid.view'))<a class="button quiet" href="{{ auth()->user()->isSuperAdmin() ? route('admin.smart-cabinet.index') : route('staff.rfid-files.index') }}">IoT status</a>@endif<span class="staff-initials" aria-label="{{ auth()->user()->name }}">{{ auth()->user()->initials() }}</span></div>
</header>
<div class="workspace">
    <nav class="side-nav" aria-label="Staff navigation">
        <div class="side-nav-scroll staff-settings-navigation">
        @if(in_array(auth()->user()->role, ['admin', 'staff'], true))
            @if(auth()->user()->hasAnyPermission(['dashboard.view', 'demographics.view']))
            <div class="sidebar-sec">
                <div class="sidebar-label">Overview</div>
                @if(auth()->user()->hasAnyPermission(['dashboard.view']))<a wire:navigate class="nav-item" href="{{ route(auth()->user()->role === 'admin' ? 'admin.dashboard' : 'staff.dashboard') }}"><x-staff-icon name="dashboard" />Dashboard</a>@endif
                @if(auth()->user()->hasAnyPermission(['demographics.view']))<a wire:navigate class="nav-item" href="{{ route('staff.demographics') }}"><x-staff-icon name="people" />Demographics</a>@endif
            </div>
            @endif
            @if(auth()->user()->hasAnyPermission(['records.view', 'voters.view', 'documents.view', 'eligibility.view', 'incidents.view', 'vawc.view', 'incidents.submit', 'vawc.submit']))
            <div class="sidebar-sec">
                <div class="sidebar-label">Records</div>
                @if(auth()->user()->hasAnyPermission(['records.view']))<a wire:navigate class="nav-item" href="{{ route('staff.residents.index') }}"><x-staff-icon name="document" />Resident Records</a>@endif
                @if(auth()->user()->hasAnyPermission(['voters.view']))<a wire:navigate class="nav-item" href="{{ route('staff.voters') }}"><x-staff-icon name="voters" />Voters</a>@endif
                @if(auth()->user()->hasAnyPermission(['documents.view']))<a wire:navigate class="nav-item" href="{{ route('staff.document-requests.index') }}"><x-staff-icon name="certificate" />Certificates &amp; Clearances</a>@endif
                @if(auth()->user()->hasAnyPermission(['eligibility.view', 'documents.view']))<a wire:navigate class="nav-item" href="{{ route('staff.request-eligibility') }}"><x-staff-icon name="clipboard" />Request Eligibility</a>@endif
                @if(auth()->user()->hasAnyPermission(['incidents.view', 'vawc.view', 'incidents.submit', 'vawc.submit']))<a wire:navigate @class(['nav-item', 'current active' => request()->routeIs('staff.incidents.*')]) aria-current="{{ request()->routeIs('staff.incidents.*') ? 'page' : 'false' }}" href="{{ route('staff.incidents.index') }}"><x-staff-icon name="alert" />Incident Reports</a>@endif
            </div>
            @endif
        @endif
            @if(auth()->user()->hasPermission('rfid.view'))
            <div class="sidebar-sec">
                <div class="sidebar-label">IoT Security</div>
                @if(auth()->user()->hasAnyPermission(['rfid.view']))<a wire:navigate @class(['nav-item', 'current active' => request()->routeIs('staff.rfid-files.*')]) href="{{ route('staff.rfid-files.index') }}"><x-staff-icon name="card" />RFID File Tracking</a>@endif
            </div>
            @endif
        @if(auth()->user()->isSuperAdmin())
            <div class="sidebar-sec">
                <div class="sidebar-label">Administration</div>
                <a wire:navigate @class(['nav-item', 'current active' => request()->routeIs('admin.smart-cabinet.*')]) href="{{ route('admin.smart-cabinet.index') }}"><x-staff-icon name="cabinet" />Smart Cabinet</a>
                <a wire:navigate @class(['nav-item', 'current active' => request()->routeIs('admin.cabinet-access.*')]) href="{{ route('admin.cabinet-access.index') }}"><x-staff-icon name="key" />Employee Cabinet Access</a>
                <a wire:navigate class="nav-item" href="{{ route('admin.audit') }}"><x-staff-icon name="audit" />Audit Log</a>
                <a wire:navigate class="nav-item" href="{{ route('admin.users.index') }}"><x-staff-icon name="user" />User Management</a>
                <a wire:navigate class="nav-item" href="{{ route('admin.settings') }}"><x-staff-icon name="settings" />Settings</a>
            </div>
        @endif
            <div class="sidebar-sec">
                <a wire:navigate class="nav-item" href="{{ route('profile.edit') }}"><x-staff-icon name="people" />My profile</a>
                <a wire:navigate class="nav-item" href="{{ route('security.edit') }}"><x-staff-icon name="shield" />Account security</a>
                <a class="nav-item portal-link staff-settings-portal" href="{{ route('home') }}" target="_blank" rel="noopener"><x-staff-icon name="globe" />Portal ng Residente</a>
            </div>
@if(auth()->user()->hasPermission('households.view') && !auth()->user()->hasPermission('records.view'))
<a wire:navigate class="nav-item" data-screen="records" href="{{ route('staff.households.page') }}"><x-staff-icon name="people" />Households</a>
            @endif

        </div>
        <div class="side-nav-user"><x-desktop-user-menu :personnel="true" /></div>
    </nav>
    <main id="main" class="main-content">
        @if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="notice error" role="alert">{{ $errors->first() }}</div>@endif
        @yield('content')
    </main>
</div>
<script>(() => { const themeButton = document.getElementById('iot-theme-toggle'); themeButton.textContent = document.documentElement.dataset.theme === 'dark' ? 'Light Mode' : 'Dark Mode'; themeButton.addEventListener('click', function () { const theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark'; document.documentElement.dataset.theme = theme; document.documentElement.classList.toggle('dark', theme === 'dark'); themeButton.textContent = theme === 'dark' ? 'Light Mode' : 'Dark Mode'; try { localStorage.setItem('smartbrgy_theme', theme); } catch (_) {} }); })();</script>
@livewireScripts
@include('partials.personnel-password-confirmation')
@include('partials.personnel-logout-confirmation')
</body>
</html>
