<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') — Barangay Anabu I-G</title>
    <script>try { document.documentElement.dataset.theme = localStorage.getItem('smartbrgy_theme') === 'dark' ? 'dark' : 'light'; } catch (_) { document.documentElement.dataset.theme = 'light'; }</script>
    <link rel="icon" type="image/jpeg" href="{{ asset('images/anabu-logo.jpg') }}">
    <link rel="stylesheet" href="{{ asset('css/admin-iot.css') }}?v={{ filemtime(public_path('css/admin-iot.css')) }}">
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>
<header class="site-header">
    <a class="brand" href="{{ in_array(auth()->user()->role, ['admin', 'staff'], true) ? route('admin.dashboard') : route('admin.rfid-files.index') }}"><img src="{{ asset('images/anabu-logo.jpg') }}" alt=""><span><strong>Barangay Anabu I-G</strong><small>Resident Information &amp; Services</small></span></a>
    <div class="header-actions"><span>{{ auth()->user()->name }}</span><button class="button quiet" id="iot-theme-toggle" type="button">Change theme</button><form action="{{ route('logout') }}" method="POST">@csrf<button class="button quiet" type="submit">Log out</button></form></div>
</header>
<div class="workspace">
    <nav class="side-nav" aria-label="Staff navigation">
        <span class="nav-heading">Workspace</span>
        @if(in_array(auth()->user()->role, ['admin', 'staff'], true))<a href="{{ route('admin.dashboard') }}">Dashboard</a>@endif
        <span class="nav-heading">IoT Security</span>
        <a @class(['current' => request()->routeIs('admin.rfid-files.*')]) href="{{ route('admin.rfid-files.index') }}">RFID File Tracking</a>
        @if(auth()->user()->isSuperAdmin())
            <span class="nav-heading">Administration</span>
            <a @class(['current' => request()->routeIs('admin.smart-cabinet.*')]) href="{{ route('admin.smart-cabinet.index') }}">Smart Cabinet</a>
            <a @class(['current' => request()->routeIs('admin.cabinet-access.*')]) href="{{ route('admin.cabinet-access.index') }}">Employee Cabinet Access</a>
            <a href="{{ route('admin.dashboard', ['screen' => 'audit']) }}">Audit Log</a>
            <a href="{{ route('admin.dashboard', ['screen' => 'users']) }}">User Management</a>
        @endif
    </nav>
    <main id="main" class="main-content">
        @if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="notice error" role="alert">{{ $errors->first() }}</div>@endif
        @yield('content')
    </main>
</div>
<script>document.getElementById('iot-theme-toggle').addEventListener('click', function () { const theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark'; document.documentElement.dataset.theme = theme; try { localStorage.setItem('smartbrgy_theme', theme); } catch (_) {} });</script>
</body>
</html>
