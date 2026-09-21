<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') | Barangay Anabu I-G</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('images/anabu-logo.jpg') }}">
    <link rel="stylesheet" href="{{ asset('css/government.css') }}">
</head>
<body class="civic-page">
    @include('partials.civic-header')
    <main class="civic-main" id="main-content" tabindex="-1">
        <div class="civic-surface">
            <div class="civic-eyebrow">Request @yield('code')</div>
            <h1 class="civic-page-title">@yield('message')</h1>
            <p class="civic-lead">The requested page could not be displayed. Check the link or reference code, or return to resident services.</p>
            <a class="civic-link" href="{{ route('home') }}">Return to resident services</a>
        </div>
    </main>
    @include('partials.civic-footer')
</body>
</html>
