@php
    $residentSignedIn = auth('resident')->check();
    $navResident = $residentSignedIn ? auth('resident')->user()->resident : null;
@endphp
<nav class="site-nav" id="site-nav" data-portal-i18n-aria-label="Main menu" aria-label="{{ __('Main menu') }}">
    <div class="site-nav-inner">
        <ul class="site-nav-list">
            <li><a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif><span data-portal-i18n="Home">{{ __('Home') }}</span></a></li>
            <li><a href="{{ route('portal.request.create') }}" data-portal-i18n-aria-label="Request a document" aria-label="{{ __('Request a document') }}" @if(request()->routeIs('portal.request.create')) aria-current="page" @endif><span data-portal-i18n="portal.navigation.request">{{ __('portal.navigation.request') }}</span></a></li>
            <li><a href="{{ $residentSignedIn ? route('portal.account') : route('portal.login') }}" @if(request()->routeIs('portal.account', 'portal.account.requests.show')) aria-current="page" @endif><span data-portal-i18n="My requests">{{ __('My requests') }}</span></a></li>
            <li><a href="{{ route('portal.information') }}" data-portal-i18n-aria-label="Help, requirements and fees" aria-label="{{ __('Help, requirements and fees') }}" @if(request()->routeIs('portal.information')) aria-current="page" @endif><span data-portal-i18n="portal.navigation.help">{{ __('portal.navigation.help') }}</span></a></li>
        </ul>
        @unless($residentSignedIn)
            <ul class="site-nav-account">
                <li><a href="{{ route('portal.login') }}" @if(request()->routeIs('portal.login')) aria-current="page" @endif><span data-portal-i18n="Log in">{{ __('Log in') }}</span></a></li>
                <li><a class="btn btn-green btn-small" href="{{ route('portal.register') }}"><span data-portal-i18n="Create account">{{ __('Create account') }}</span></a></li>
            </ul>
        @endunless
    </div>
</nav>
@if($residentSignedIn)
<div class="rp-account-menu" data-account-menu>
    <button type="button" class="rp-account-button" aria-expanded="false" aria-controls="account-menu-list" data-account-menu-button>
        @if($navResident?->has_photo)
            <img src="{{ route('portal.profile.photo') }}" width="32" height="32" alt="">
        @else
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
        @endif
        <span class="rp-account-name">@if($navResident){{ $navResident->first_name }} {{ mb_substr((string) $navResident->last_name, 0, 1) }}.@else<span data-portal-i18n="Aking account">{{ __('Aking account') }}</span>@endif</span>
        <span class="sr-only"><span data-portal-i18n="Account menu">{{ __('Account menu') }}</span></span>
        <svg class="rp-account-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
    </button>
    <ul class="rp-account-list" id="account-menu-list" hidden>
        <li><a href="{{ route('portal.account.home') }}" @if(request()->routeIs('portal.account.home')) aria-current="page" @endif><span data-portal-i18n="Aking account">{{ __('Aking account') }}</span></a></li>
        <li><a href="{{ route('portal.profile') }}" @if(request()->routeIs('portal.profile')) aria-current="page" @endif><span data-portal-i18n="Aking profile">{{ __('Aking profile') }}</span></a></li>
        <li><a href="{{ route('password.request', ['from' => 'portal']) }}"><span data-portal-i18n="Palitan ang password">{{ __('Palitan ang password') }}</span></a></li>
        <li class="rp-account-separator"><button type="button" data-resident-logout><span data-portal-i18n="Log out">{{ __('Log out') }}</span></button></li>
    </ul>
</div>
<dialog class="resident-logout-dialog" id="resident-logout-dialog" aria-labelledby="resident-logout-title">
    <h2 id="resident-logout-title"><span data-portal-i18n="Mag-log out?">{{ __('Mag-log out?') }}</span></h2>
    <p><span data-portal-i18n="Matatapos ang session ninyo at mabubura ang anumang hindi pa na-submit na request form.">{{ __('Matatapos ang session ninyo at mabubura ang anumang hindi pa na-submit na request form.') }}</span></p>
    <form method="POST" action="{{ route('portal.logout') }}" data-resident-form data-resident-logout-form>
        @csrf
        <div class="btn-row"><button class="btn btn-outline" type="button" data-resident-cancel autofocus><span data-portal-i18n="Cancel">{{ __('Cancel') }}</span></button><button class="btn btn-green" type="submit"><span data-portal-i18n="Log out">{{ __('Log out') }}</span></button></div>
    </form>
</dialog>
@endif
