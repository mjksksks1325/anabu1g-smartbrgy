<nav class="resident-navigation" aria-label="Resident services">
<a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Home</a><a href="{{ route('home') }}#services">Services</a><a href="{{ route('home') }}#population">Population</a><a href="{{ route('home') }}#announcements">News &amp; announcements</a><a href="{{ route('home') }}#contacts">Contacts</a><a href="{{ route('portal.information') }}" @if(request()->routeIs('portal.information')) aria-current="page" @endif>Barangay information</a><a href="{{ route('portal.information') }}#requirements">Requirements</a>
@if(auth('resident')->check())
<a href="{{ route('portal.request.create') }}" @if(request()->routeIs('portal.request.create')) aria-current="page" @endif>Request document</a><a href="{{ route('portal.account') }}" @if(request()->routeIs('portal.account')) aria-current="page" @endif>My requests</a><a href="{{ route('portal.profile') }}" @if(request()->routeIs('portal.profile')) aria-current="page" @endif>Profile</a>
<button type="button" class="btn btn-outline" data-resident-logout>Log out</button>
@else
<a href="{{ route('portal.request.create') }}">Request document</a><a href="{{ route('portal.login') }}" @if(request()->routeIs('portal.login')) aria-current="page" @endif>Resident login</a><a class="resident-nav-cta" href="{{ route('portal.register') }}">Create account</a>
@endif
</nav>
@if(auth('resident')->check())
<dialog class="resident-logout-dialog" id="resident-logout-dialog" aria-labelledby="resident-logout-title">
<h2 id="resident-logout-title">Log out of the Resident Portal?</h2><p>Your session will end and the request form will be cleared.</p>
<form method="POST" action="{{ route('portal.logout') }}" data-resident-form data-resident-logout-form>@csrf
<div class="btn-row"><button class="btn btn-outline" type="button" data-resident-cancel autofocus>Cancel</button><button class="btn btn-green" type="submit">Log out</button></div>
</form></dialog>
@endif
