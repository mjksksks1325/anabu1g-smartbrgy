<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>@include('partials.head')</head>
    <body class="civic-page min-h-screen antialiased">
        @include('partials.civic-header')
        <main id="main-content" class="civic-auth" tabindex="-1">
            <aside class="civic-auth-aside">
                <div class="civic-eyebrow">Barangay administration</div>
                <h1>Public service.<br>Connected community.</h1>
                <p>Manage resident records, document requests, and barangay services in one place.</p>
                <p class="civic-auth-note">For authorized barangay personnel. Residents can request documents through the resident services portal.</p>
            </aside>
            <div class="civic-auth-form">{{ $slot }}</div>
        </main>
        @include('partials.civic-footer')
        @persist('toast')
            <flux:toast.group><flux:toast /></flux:toast.group>
        @endpersist
        @fluxScripts
    </body>
</html>
