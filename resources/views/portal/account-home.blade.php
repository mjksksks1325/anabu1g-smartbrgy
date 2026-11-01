@extends('layouts.portal')
@section('title', __('Aking account'))
@section('band')
<x-portal.page-band title="Aking account" />
@endsection
@section('content')
<div class="v2-account" data-resident-private>
    <section class="v2-card v2-account-card" aria-labelledby="account-name">
        @if($resident->has_photo)
            <img class="v2-avatar" src="{{ route('portal.profile.photo') }}" width="72" height="72" alt="">
        @else
            <span class="v2-avatar" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span>
        @endif
        <h2 id="account-name">{{ $resident->full_name }}</h2>
        <p class="v2-summary-note"><span data-portal-i18n="Resident number">{{ __('Resident number') }}</span> <span class="v2-ref">{{ $resident->resident_number }}</span></p>
        <button type="button" class="btn btn-outline" data-resident-logout><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 4H5v16h5M14 8l4 4-4 4M18 12H9"/></svg><span data-portal-i18n="Log out">{{ __('Log out') }}</span></button>
    </section>
    <nav class="v2-card v2-menu-list" aria-label="{{ __('Aking account') }}" data-portal-i18n-aria-label="Aking account">
        <ul class="v2-links v2-links-icon">
            <li><a href="{{ route('portal.account') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6" y="4" width="12" height="17" rx="2"/><path d="M9 4V3h6v1M9 10h6M9 14h6M9 18h3"/></svg><span data-portal-i18n="My requests">{{ __('My requests') }}</span></a></li>
            <li><a href="{{ route('portal.profile') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg><span data-portal-i18n="Aking profile">{{ __('Aking profile') }}</span></a></li>
            <li><a href="{{ route('password.request', ['from' => 'portal']) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg><span data-portal-i18n="Palitan ang password">{{ __('Palitan ang password') }}</span></a></li>
            <li><a href="{{ route('portal.information') }}#help"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 0 1 5 0c0 2-2.5 2-2.5 4M12 17h.01"/></svg><span data-portal-i18n="Get help">{{ __('Get help') }}</span></a></li>
        </ul>
    </nav>
</div>
@endsection
