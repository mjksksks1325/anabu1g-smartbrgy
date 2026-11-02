@php
    $residentSignedIn = auth('resident')->check();
    $navResident = $residentSignedIn ? auth('resident')->user()->resident : null;
@endphp
<nav class="site-nav" id="site-nav" data-portal-i18n-aria-label="Main menu" aria-label="{{ __('Main menu') }}">
    <div class="site-nav-inner">
        <ul class="site-nav-list">
            @if($residentSignedIn)
                <li><a href="{{ route('portal.account.home') }}" @if(request()->routeIs('portal.account.home')) aria-current="page" @endif><span data-portal-i18n="Aking account">{{ __('Aking account') }}</span></a></li>
                <li><a href="{{ route('portal.request.create') }}" data-portal-i18n-aria-label="Request a document" aria-label="{{ __('Request a document') }}" @if(request()->routeIs('portal.request.create')) aria-current="page" @endif><span data-portal-i18n="portal.navigation.request">{{ __('portal.navigation.request') }}</span></a></li>
                <li><a href="{{ route('portal.account') }}" @if(request()->routeIs('portal.account', 'portal.account.requests.show')) aria-current="page" @endif><span data-portal-i18n="My requests">{{ __('My requests') }}</span></a></li>
            @else
                <li><a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif><span data-portal-i18n="Home">{{ __('Home') }}</span></a></li>
                <li><a href="{{ route('portal.information') }}#requirements" data-portal-i18n-aria-label="Requirements and fees" aria-label="{{ __('Requirements and fees') }}"><span data-portal-i18n="portal.navigation.requirements">{{ __('portal.navigation.requirements') }}</span></a></li>
            @endif
            <li><a href="{{ route('portal.information') }}" data-portal-i18n-aria-label="Help, requirements and fees" aria-label="{{ __('Help, requirements and fees') }}" @if(request()->routeIs('portal.information')) aria-current="page" @endif><span data-portal-i18n="portal.navigation.help">{{ __('portal.navigation.help') }}</span></a></li>
        </ul>
        <ul class="site-nav-account">
            @if($residentSignedIn)
                <li><a href="{{ route('portal.profile') }}" @if(request()->routeIs('portal.profile')) aria-current="page" @endif><span data-portal-i18n="Profile">{{ __('Profile') }}</span></a></li>
                <li><button type="button" class="btn btn-outline btn-small" data-resident-logout><span data-portal-i18n="Log out">{{ __('Log out') }}</span></button></li>
            @else
                <li><a href="{{ route('portal.login') }}" @if(request()->routeIs('portal.login')) aria-current="page" @endif><span data-portal-i18n="Log in">{{ __('Log in') }}</span></a></li>
                <li><a href="{{ route('portal.register') }}" @if(request()->routeIs('portal.register')) aria-current="page" @endif><span data-portal-i18n="Create account">{{ __('Create account') }}</span></a></li>
            @endif
        </ul>
    </div>
</nav>
<div class="site-header-actions">
    @include('partials.portal-language-toggle')
    @if($residentSignedIn)
        <a class="v2-account-pill" href="{{ route('portal.account.home') }}" @if(request()->routeIs('portal.account.home')) aria-current="page" @endif>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
            @if($navResident)
                <span>{{ $navResident->first_name }} {{ mb_substr((string) $navResident->last_name, 0, 1) }}.</span>
                <span class="sr-only">· <span data-portal-i18n="Aking account">{{ __('Aking account') }}</span></span>
            @else
                <span data-portal-i18n="Aking account">{{ __('Aking account') }}</span>
            @endif
        </a>
    @else
        <a class="v2-login-pill" href="{{ route('portal.login') }}"><span data-portal-i18n="Log in">{{ __('Log in') }}</span></a>
    @endif
</div>
@if($residentSignedIn)
<dialog class="resident-logout-dialog" id="resident-logout-dialog" aria-labelledby="resident-logout-title">
    <h2 id="resident-logout-title"><span data-portal-i18n="Mag-log out?">{{ __('Mag-log out?') }}</span></h2>
    <p><span data-portal-i18n="Matatapos ang session ninyo at mabubura ang anumang hindi pa na-submit na request form.">{{ __('Matatapos ang session ninyo at mabubura ang anumang hindi pa na-submit na request form.') }}</span></p>
    <form method="POST" action="{{ route('portal.logout') }}" data-resident-form data-resident-logout-form>
        @csrf
        <div class="btn-row"><button class="btn btn-outline" type="button" data-resident-cancel autofocus><span data-portal-i18n="Cancel">{{ __('Cancel') }}</span></button><button class="btn btn-green" type="submit"><span data-portal-i18n="Log out">{{ __('Log out') }}</span></button></div>
    </form>
</dialog>
@endif
