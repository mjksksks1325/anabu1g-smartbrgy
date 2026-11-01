@extends('layouts.portal')
@section('title', __('Resident Portal'))
@php($residentSignedIn = auth('resident')->check())

@section('band')
<div class="v2-hero">
    <div class="v2-hero-inner">
        @if($resident)
            <p class="v2-hero-greeting"><span data-portal-i18n="Magandang araw, :name." data-portal-params="{{ json_encode(['name' => $resident->first_name]) }}">{{ __('Magandang araw, :name.', ['name' => $resident->first_name]) }}</span></p>
        @else
            <p class="v2-hero-greeting"><span data-portal-i18n="Maligayang pagdating!">{{ __('Maligayang pagdating!') }}</span></p>
        @endif
        <h1 id="portal-title"><span data-portal-i18n="Ano ang kailangan mo ngayon?">{{ __('Ano ang kailangan mo ngayon?') }}</span></h1>
    </div>
</div>
@endsection

@section('content')
<div @class(['v2-home', 'v2-home-guest' => ! $residentSignedIn])>
    <nav class="v2-tasks" id="services" aria-labelledby="tasks-title">
        <h2 id="tasks-title" class="sr-only"><span data-portal-i18n="Online services">{{ __('Online services') }}</span></h2>
        <a class="v2-task v2-task-primary" href="{{ route('portal.request.create') }}">
            <span class="v2-task-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/></svg></span>
            <span class="v2-task-title"><span data-portal-i18n="Request a document">{{ __('Request a document') }}</span></span>
            <span class="v2-task-text"><span data-portal-i18n="Barangay Clearance, Residency, Indigency, Barangay ID at iba pa.">{{ __('Barangay Clearance, Residency, Indigency, Barangay ID at iba pa.') }}</span></span>
        </a>
        <a class="v2-task" href="{{ $residentSignedIn ? route('portal.account') : route('portal.login') }}" data-home-status-task>
            <span class="v2-task-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="12" height="17" rx="2"/><path d="M9 4V3h6v1M9 10h6M9 14h6M9 18h3"/></svg></span>
            <span class="v2-task-title"><span data-portal-i18n="My requests">{{ __('My requests') }}</span></span>
            @if($latestRequest)
                <span class="v2-task-text"><span data-portal-i18n="Huling request:">{{ __('Huling request:') }}</span> <span data-portal-i18n="{{ $latestRequest->document_type }}">{{ __($latestRequest->document_type) }}</span></span>
                @php($latestLabel = config('portal.request_statuses.'.$latestRequest->status.'.label') ?? ucfirst(str_replace('_', ' ', $latestRequest->status)))
                <span class="v2-status" data-status="{{ $latestRequest->status }}"><span class="sr-only"><span data-portal-i18n="Status:">{{ __('Status:') }}</span> </span><span data-portal-i18n="{{ $latestLabel }}">{{ __($latestLabel) }}</span></span>
            @elseif($residentSignedIn)
                <span class="v2-task-text"><span data-portal-i18n="Wala pang request">{{ __('Wala pang request') }}</span></span>
            @else
                <span class="v2-task-text"><span data-portal-i18n="Tingnan ang status ng mga na-submit mo.">{{ __('Tingnan ang status ng mga na-submit mo.') }}</span></span>
            @endif
        </a>
    </nav>

    @unless($residentSignedIn)
        <section class="v2-card v2-login-card" aria-labelledby="login-first-title">
            <h2 id="login-first-title"><span data-portal-i18n="Mag-log in muna">{{ __('Mag-log in muna') }}</span></h2>
            <p><span data-portal-i18n="Kailangan ng resident account na naka-link sa record ninyo sa barangay.">{{ __('Kailangan ng resident account na naka-link sa record ninyo sa barangay.') }}</span></p>
            <a class="btn btn-green" href="{{ route('portal.login') }}"><span data-portal-i18n="Log in">{{ __('Log in') }}</span></a>
            <a class="btn btn-outline" href="{{ route('portal.register') }}"><span data-portal-i18n="Create account">{{ __('Create account') }}</span></a>
        </section>
    @endunless

    <section class="v2-callout v2-home-callout" aria-labelledby="before-visit-title">
        <svg class="v2-callout-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5h.01"/></svg>
        <div>
            <h2 id="before-visit-title"><span data-portal-i18n="Bago pumunta sa Barangay Hall">{{ __('Bago pumunta sa Barangay Hall') }}</span></h2>
            <p><span data-portal-i18n="Hintaying maging Ready for release ang request bago pumunta.">{{ __('Hintaying maging Ready for release ang request bago pumunta.') }}</span> <span data-portal-i18n="Dalhin ang original na valid ID at ang reference number.">{{ __('Dalhin ang original na valid ID at ang reference number.') }}</span> <span data-portal-i18n="Sa Barangay Hall babayaran ang fee, kung mayroon. Walang online payment.">{{ __('Sa Barangay Hall babayaran ang fee, kung mayroon. Walang online payment.') }}</span></p>
        </div>
    </section>

    <section class="v2-more" aria-labelledby="more-title">
        <h2 id="more-title"><span data-portal-i18n="Iba pa">{{ __('Iba pa') }}</span></h2>
        <ul class="v2-link-grid">
            @if($residentSignedIn)
                <li><a href="{{ route('portal.account.home') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg><span data-portal-i18n="Aking account">{{ __('Aking account') }}</span></a></li>
            @endif
            <li><a href="{{ route('portal.information') }}#requirements"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/></svg><span data-portal-i18n="Requirements and fees">{{ __('Requirements and fees') }}</span></a></li>
            <li><a href="{{ route('portal.officials') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M6 16a3 3 0 0 1 6 0M14 10h4M14 14h4"/></svg><span data-portal-i18n="Barangay officials">{{ __('Barangay officials') }}</span></a></li>
            <li><a href="{{ route('portal.information') }}#help"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 0 1 5 0c0 2-2.5 2-2.5 4M12 17h.01"/></svg><span data-portal-i18n="Get help">{{ __('Get help') }}</span></a></li>
            <li><a href="{{ route('portal.information') }}#contacts"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg><span data-portal-i18n="Emergency hotlines">{{ __('Emergency hotlines') }}</span></a></li>
        </ul>
    </section>

    <section class="v2-card v2-home-announcements" id="announcements" aria-labelledby="announcements-title">
        <div class="v2-card-head">
            <h2 id="announcements-title"><span data-portal-i18n="Announcements">{{ __('Announcements') }}</span></h2>
            <a href="{{ route('portal.announcements.index') }}"><span data-portal-i18n="Tingnan ang lahat ng anunsyo">{{ __('Tingnan ang lahat ng anunsyo') }}</span></a>
        </div>
        @if($announcements->isEmpty())
            <p><span data-portal-i18n="Wala pang bagong anunsyo mula sa barangay.">{{ __('Wala pang bagong anunsyo mula sa barangay.') }}</span></p>
        @else
            <ul class="v2-announcements">
                @foreach($announcements as $announcement)
                    <li>
                        <time datetime="{{ $announcement->published_at->toIso8601String() }}">{{ $announcement->published_at->format('F j, Y') }}</time>
                        <h3>{{ $announcement->title }}</h3>
                        <p>{{ $announcement->body }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
        <p class="v2-summary-note"><span data-portal-i18n="Nasa opisyal na website ng City of Imus ang mga balita at abiso ng lungsod.">{{ __('Nasa opisyal na website ng City of Imus ang mga balita at abiso ng lungsod.') }}</span> <a href="https://cityofimus.gov.ph/" target="_blank" rel="noopener noreferrer"><span data-portal-i18n="Buksan ang City of Imus website">{{ __('Buksan ang City of Imus website') }}</span></a></p>
    </section>
</div>
@endsection
