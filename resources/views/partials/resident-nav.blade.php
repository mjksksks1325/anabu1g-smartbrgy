@php($residentSignedIn = auth('resident')->check())
<nav class="site-nav" id="site-nav" aria-label="Main menu">
    <div class="site-nav-inner">
        <ul class="site-nav-list">
            <li><a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Home</a></li>
            <li><a href="{{ route('portal.request.create') }}" @if(request()->routeIs('portal.request.create')) aria-current="page" @endif>Request a document</a></li>
            <li><a href="{{ $residentSignedIn ? route('portal.account') : route('portal.login') }}" @if(request()->routeIs('portal.account')) aria-current="page" @endif>My requests</a></li>
            <li><a href="{{ route('portal.information') }}#requirements" @if(request()->routeIs('portal.information')) aria-current="page" @endif>Requirements and fees</a></li>
            <li><a href="{{ route('portal.information') }}#help">Get help</a></li>
        </ul>
        <ul class="site-nav-account">
            @if($residentSignedIn)
                <li><a href="{{ route('portal.profile') }}" @if(request()->routeIs('portal.profile')) aria-current="page" @endif>Profile</a></li>
                <li><button type="button" class="btn btn-outline btn-small" data-resident-logout>Log out</button></li>
            @else
                <li><a href="{{ route('portal.login') }}" @if(request()->routeIs('portal.login')) aria-current="page" @endif>Log in</a></li>
                <li><a class="btn btn-green btn-small" href="{{ route('portal.register') }}">Create account</a></li>
            @endif
        </ul>
    </div>
</nav>
@if($residentSignedIn)
<dialog class="resident-logout-dialog" id="resident-logout-dialog" aria-labelledby="resident-logout-title">
    <h2 id="resident-logout-title">Mag-log out?</h2>
    <p>Matatapos ang session ninyo at mabubura ang anumang hindi pa na-submit na request form.</p>
    <form method="POST" action="{{ route('portal.logout') }}" data-resident-form data-resident-logout-form>
        @csrf
        <div class="btn-row"><button class="btn btn-outline" type="button" data-resident-cancel autofocus>Cancel</button><button class="btn btn-green" type="submit">Log out</button></div>
    </form>
</dialog>
@endif
