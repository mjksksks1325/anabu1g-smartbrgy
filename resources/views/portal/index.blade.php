@extends('layouts.portal')
@section('title', __('Resident Portal'))
@php($residentSignedIn = auth('resident')->check())

@section('content')
<div class="v2-home">
    <div class="v2-home-main">
        <section aria-labelledby="portal-title">
            @if($resident)
                <p class="v2-eyebrow"><span data-portal-i18n="Magandang araw, :name." data-portal-params="{{ json_encode(['name' => $resident->first_name]) }}">{{ __('Magandang araw, :name.', ['name' => $resident->first_name]) }}</span></p>
            @else
                <p class="v2-eyebrow"><span data-portal-i18n="Resident Portal ng Barangay Anabu I-G">{{ __('Resident Portal ng Barangay Anabu I-G') }}</span></p>
            @endif
            <h1 id="portal-title" class="v2-home-title"><span data-portal-i18n="Ano ang kailangan mo ngayon?">{{ __('Ano ang kailangan mo ngayon?') }}</span></h1>
        </section>

        <nav class="v2-tasks" id="services" aria-labelledby="tasks-title">
            <h2 id="tasks-title" class="sr-only"><span data-portal-i18n="Online services">{{ __('Online services') }}</span></h2>
            <a class="v2-task v2-task-primary" href="{{ route('portal.request.create') }}">
                <span class="v2-task-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M12 11v6M9 14h6"/></svg></span>
                <span class="v2-task-title"><span data-portal-i18n="Request a document">{{ __('Request a document') }}</span></span>
                <span class="v2-task-text"><span data-portal-i18n="Barangay Clearance, Residency, Indigency, Barangay ID at iba pa.">{{ __('Barangay Clearance, Residency, Indigency, Barangay ID at iba pa.') }}</span></span>
            </a>
            <a class="v2-task" href="{{ $residentSignedIn ? route('portal.account') : route('portal.login') }}" data-home-status-task>
                <span class="v2-task-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
                <span class="v2-task-title"><span data-portal-i18n="Check status">{{ __('Check status') }}</span></span>
                @if($latestRequest)
                    <span class="v2-task-text"><span data-portal-i18n="Huling request:">{{ __('Huling request:') }}</span> <span data-portal-i18n="{{ $latestRequest->document_type }}">{{ __($latestRequest->document_type) }}</span></span>
                    @php($latestLabel = config('portal.request_statuses.'.$latestRequest->status.'.label') ?? ucfirst(str_replace('_', ' ', $latestRequest->status)))
                    <span class="v2-status" data-status="{{ $latestRequest->status }}"><span class="sr-only"><span data-portal-i18n="Status:">{{ __('Status:') }}</span> </span><span data-portal-i18n="{{ $latestLabel }}">{{ __($latestLabel) }}</span></span>
                @elseif($residentSignedIn)
                    <span class="v2-task-text"><span data-portal-i18n="Wala pang request">{{ __('Wala pang request') }}</span></span>
                @else
                    <span class="v2-task-text"><span data-portal-i18n="Mag-log in para makita ang status ng mga request mo.">{{ __('Mag-log in para makita ang status ng mga request mo.') }}</span></span>
                @endif
            </a>
        </nav>
        @unless($residentSignedIn)
            <p class="v2-account-note"><span data-portal-i18n="Wala pang account?">{{ __('Wala pang account?') }}</span> <a href="{{ route('portal.register') }}"><span data-portal-i18n="Create account">{{ __('Create account') }}</span></a></p>
        @endunless

        <section class="v2-card v2-card-info" aria-labelledby="before-visit-title">
            <h2 id="before-visit-title"><span data-portal-i18n="Bago pumunta sa Barangay Hall">{{ __('Bago pumunta sa Barangay Hall') }}</span></h2>
            <ul class="v2-list">
                <li><span data-portal-i18n="Hintaying maging Ready for release ang request bago pumunta.">{{ __('Hintaying maging Ready for release ang request bago pumunta.') }}</span></li>
                <li><span data-portal-i18n="Dalhin ang original na valid ID at ang reference number.">{{ __('Dalhin ang original na valid ID at ang reference number.') }}</span></li>
                <li><span data-portal-i18n="Sa Barangay Hall babayaran ang fee, kung mayroon. Walang online payment.">{{ __('Sa Barangay Hall babayaran ang fee, kung mayroon. Walang online payment.') }}</span></li>
                <li><span data-portal-i18n="Office hours:">{{ __('Office hours:') }}</span> <span class="tag-unconfirmed"><span data-portal-i18n="Hindi pa kumpirmado">{{ __('Hindi pa kumpirmado') }}</span></span></li>
            </ul>
        </section>

        <section class="v2-card" id="announcements" aria-labelledby="announcements-title">
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

    <aside class="v2-home-aside" aria-label="{{ __('Barangay Hall') }}" data-portal-i18n-aria-label="Barangay Hall">
        <figure class="v2-hall-photo"><img src="{{ asset('images/barangay-hall-anabu-1g.jpg') }}" width="1536" height="1024" data-portal-i18n-alt="Barangay Hall ng Anabu I-G" alt="{{ __('Barangay Hall ng Anabu I-G') }}"></figure>
        <section class="v2-card" aria-labelledby="office-card-title">
            <h2 id="office-card-title"><span data-portal-i18n="Barangay Anabu I-G Hall">{{ __('Barangay Anabu I-G Hall') }}</span></h2>
            <p class="v2-address"><span data-portal-i18n="Barangay Anabu I-G, Lungsod ng Imus, Cavite">{{ __('Barangay Anabu I-G, Lungsod ng Imus, Cavite') }}</span></p>
            <p><a href="https://www.google.com/maps/search/?api=1&amp;query=Barangay+Anabu+I-G%2C+Imus%2C+Cavite" target="_blank" rel="noopener noreferrer"><span data-portal-i18n="Buksan sa Google Maps">{{ __('Buksan sa Google Maps') }}</span> <span class="sr-only"><span data-portal-i18n="(opens in a new tab)">{{ __('(opens in a new tab)') }}</span></span></a></p>
        </section>
        <section class="v2-card" aria-labelledby="help-card-title">
            <h2 id="help-card-title"><span data-portal-i18n="Kailangan ng tulong?">{{ __('Kailangan ng tulong?') }}</span></h2>
            <ul class="v2-links">
                <li><a href="{{ route('portal.information') }}#requirements"><span data-portal-i18n="Requirements and fees">{{ __('Requirements and fees') }}</span></a></li>
                <li><a href="{{ route('portal.information') }}#help"><span data-portal-i18n="Problema sa account o record">{{ __('Problema sa account o record') }}</span></a></li>
                <li><a href="{{ route('portal.information') }}#contacts"><span data-portal-i18n="Emergency hotlines">{{ __('Emergency hotlines') }}</span></a></li>
            </ul>
        </section>
    </aside>
</div>
@endsection
