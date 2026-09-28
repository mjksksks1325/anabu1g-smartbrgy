<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>@include('partials.head')<link rel="stylesheet" href="{{ asset('css/figma-tokens.css') }}?v={{ filemtime(public_path('css/figma-tokens.css')) }}"><link rel="stylesheet" href="{{ asset('css/figma-auth.css') }}?v={{ filemtime(public_path('css/figma-auth.css')) }}"></head>
    <body class="civic-page staff-auth-page min-h-screen antialiased">
        <a href="#main-content" class="skip-link">Skip to main content</a>
        <div class="staff-auth-gov"><div><span><strong>GOVPH</strong> <span aria-hidden="true">|</span> Republic of the Philippines</span><span>Authorized personnel only</span></div></div>
        <main id="main-content" class="civic-auth" tabindex="-1">
            <aside class="civic-auth-aside">
                <div class="staff-auth-brand"><img src="{{ asset('images/anabu-logo.jpg') }}" alt="Barangay Anabu I-G seal"><div><span>Official internal system</span><strong>Barangay Anabu I-G</strong></div></div>
                <h1>Barangay Operations Portal</h1>
                <p>A secure workspace for processing resident requests, managing records, and coordinating frontline services.</p>
                <div class="staff-auth-features"><div><strong>Secure access</strong><span>Role-based staff permissions</span></div><div><strong>Central workspace</strong><span>Requests and resident records</span></div></div>
                <a class="staff-auth-resident-link" href="{{ route('home') }}">Resident services</a>
            </aside>
            <div class="civic-auth-form">{{ $slot }}</div>
        </main>
        @persist('toast')
            <flux:toast.group><flux:toast /></flux:toast.group>
        @endpersist
        @fluxScripts
    </body>
</html>
