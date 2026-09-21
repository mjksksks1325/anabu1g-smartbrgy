<a href="#main-content" class="skip-link">Skip to main content</a>
<header class="civic-masthead">
    <div class="civic-masthead-inner">
        <img class="civic-seal" src="{{ asset('images/anabu-logo.jpg') }}" alt="Barangay Anabu I-G seal">
        <a class="civic-brand" href="{{ route('home') }}">
            <small>City of Imus &middot; Province of Cavite</small>
            <strong>Barangay Anabu I-G</strong>
        </a>
        <nav class="civic-nav" aria-label="Main navigation">
            <a href="{{ route('home') }}">Resident services</a>
            @auth
                <a href="{{ route('admin.dashboard') }}">Administration</a>
            @else
                <a href="{{ route('login') }}">Staff sign in</a>
            @endauth
        </nav>
    </div>
</header>
