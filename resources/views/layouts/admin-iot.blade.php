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
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>
<header class="site-header">
    <a class="brand" href="{{ in_array(auth()->user()->role, ['admin', 'staff'], true) ? route('admin.dashboard') : route('admin.rfid-files.index') }}"><img src="{{ asset('images/anabu-logo.jpg') }}" alt="Barangay Anabu I-G seal"><span><strong>Barangay Anabu I-G</strong><small>STAFF WORKSPACE</small></span></a>
    <div class="workspace-title"><span>Staff workspace</span><strong>@yield('title')</strong></div>
    <div class="header-actions"><button class="button quiet" id="iot-theme-toggle" type="button">Dark Mode</button><a class="button quiet" href="{{ auth()->user()->isSuperAdmin() ? route('admin.smart-cabinet.index') : route('admin.rfid-files.index') }}">IoT status</a><form action="{{ route('logout') }}" method="POST">@csrf<button class="staff-initials" type="submit" title="Log out" aria-label="Log out">{{ auth()->user()->initials() }}</button></form></div>
</header>
<div class="workspace">
    <nav class="side-nav" aria-label="Staff navigation">
        <div class="side-nav-scroll staff-settings-navigation">
        @if(in_array(auth()->user()->role, ['admin', 'staff'], true))
            <div class="sidebar-sec">
                <div class="sidebar-label">Overview</div>
                <a class="nav-item" href="{{ route('admin.dashboard') }}"><x-staff-icon name="dashboard" />Dashboard</a>
                <a class="nav-item" href="{{ route('admin.demographics') }}"><x-staff-icon name="people" />Demographics</a>
            </div>
            <div class="sidebar-sec">
                <div class="sidebar-label">Records</div>
                <a class="nav-item" href="{{ route('admin.residents.index') }}"><x-staff-icon name="document" />Resident Records</a>
                <a class="nav-item" href="{{ route('admin.voters') }}"><x-staff-icon name="voters" />Voters</a>
                <a class="nav-item" href="{{ route('admin.document-requests.index') }}"><x-staff-icon name="certificate" />Certificates &amp; Clearances</a>
                <a class="nav-item" href="{{ route('admin.request-eligibility') }}"><x-staff-icon name="clipboard" />Request Eligibility</a>
                <a class="nav-item" href="{{ route('admin.incidents.index') }}"><x-staff-icon name="alert" />Incident Reports</a>
            </div>
        @endif
            <div class="sidebar-sec">
                <div class="sidebar-label">IoT Security</div>
                <a @class(['nav-item', 'current active' => request()->routeIs('admin.rfid-files.*')]) href="{{ route('admin.rfid-files.index') }}"><x-staff-icon name="card" />RFID File Tracking</a>
            </div>
        @if(auth()->user()->isSuperAdmin())
            <div class="sidebar-sec">
                <div class="sidebar-label">Administration</div>
                <a @class(['nav-item', 'current active' => request()->routeIs('admin.smart-cabinet.*')]) href="{{ route('admin.smart-cabinet.index') }}"><x-staff-icon name="cabinet" />Smart Cabinet</a>
                <a @class(['nav-item', 'current active' => request()->routeIs('admin.cabinet-access.*')]) href="{{ route('admin.cabinet-access.index') }}"><x-staff-icon name="key" />Employee Cabinet Access</a>
                <a class="nav-item" href="{{ route('admin.audit') }}"><x-staff-icon name="audit" />Audit Log</a>
                <a class="nav-item" href="{{ route('admin.users.index') }}"><x-staff-icon name="user" />User Management</a>
                <a class="nav-item" href="{{ route('admin.settings') }}"><x-staff-icon name="settings" />Settings</a>
            </div>
        @endif
            <div class="sidebar-sec">
                <a class="nav-item" href="{{ route('profile.edit') }}"><x-staff-icon name="people" />My profile</a>
                <a class="nav-item" href="{{ route('security.edit') }}"><x-staff-icon name="shield" />Account security</a>
                <a class="nav-item portal-link staff-settings-portal" href="{{ route('home') }}" target="_blank" rel="noopener"><x-staff-icon name="globe" />Portal ng Residente</a>
            </div>
        </div>
        <div class="side-nav-user"><span class="staff-initials">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(auth()->user()->name, 0, 2)) }}</span><span><strong>{{ auth()->user()->name }}</strong><small>{{ ucfirst(str_replace('_', ' ', auth()->user()->role)) }}</small></span></div>
    </nav>
    <main id="main" class="main-content">
        @if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="notice error" role="alert">{{ $errors->first() }}</div>@endif
        @yield('content')
    </main>
</div>
<script>const themeButton = document.getElementById('iot-theme-toggle'); themeButton.textContent = document.documentElement.dataset.theme === 'dark' ? 'Light Mode' : 'Dark Mode'; themeButton.addEventListener('click', function () { const theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark'; document.documentElement.dataset.theme = theme; document.documentElement.classList.toggle('dark', theme === 'dark'); themeButton.textContent = theme === 'dark' ? 'Light Mode' : 'Dark Mode'; try { localStorage.setItem('smartbrgy_theme', theme); } catch (_) {} });</script>
</body>
</html>
