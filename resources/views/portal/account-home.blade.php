@extends('layouts.portal')
@section('title', __('Aking account'))
@php
    $summary = [
        ['In progress', ($statusCounts['pending'] ?? 0) + ($statusCounts['processing'] ?? 0) + ($statusCounts['approved'] ?? 0), 'processing'],
        ['Ready for release', $statusCounts['ready_for_release'] ?? 0, 'ready_for_release'],
        ['Released', $statusCounts['released'] ?? 0, 'released'],
        ['Not approved', $statusCounts['rejected'] ?? 0, 'rejected'],
    ];
    $readyCount = $statusCounts['ready_for_release'] ?? 0;
@endphp
@section('band')
<x-portal.page-band title="Aking account" eyebrow="Resident Portal">
    <p><span data-portal-i18n="Magandang araw, :name." data-portal-params="{{ json_encode(['name' => $resident->first_name]) }}">{{ __('Magandang araw, :name.', ['name' => $resident->first_name]) }}</span> <span data-portal-i18n="Ano ang gusto mong gawin ngayon?">{{ __('Ano ang gusto mong gawin ngayon?') }}</span></p>
</x-portal.page-band>
@endsection
@section('content')
<div class="rp-dashboard" data-resident-private>
    <div class="rp-dashboard-main">
        @if($readyCount > 0)
            <a class="rp-alert-ready" href="{{ route('portal.account') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="currentColor"/><path d="M7 12.5l3.2 3.2L17 9" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span><strong><span data-portal-i18n="May dokumentong puwede nang kunin.">{{ __('May dokumentong puwede nang kunin.') }}</span></strong> <span data-portal-i18n="Tingnan kung alin at paano ito kunin.">{{ __('Tingnan kung alin at paano ito kunin.') }}</span></span>
            </a>
        @endif

        <nav class="rp-actions" aria-label="{{ __('Online services') }}" data-portal-i18n-aria-label="Online services">
            <a class="rp-action rp-action-primary" href="{{ route('portal.request.create') }}">
                <span class="rp-action-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M12 11v6M9 14h6"/></svg></span>
                <span class="rp-action-title"><span data-portal-i18n="Request a document">{{ __('Request a document') }}</span></span>
                <span class="rp-action-text"><span data-portal-i18n="Barangay Clearance, Residency, Indigency, Barangay ID at iba pa.">{{ __('Barangay Clearance, Residency, Indigency, Barangay ID at iba pa.') }}</span></span>
            </a>
            <a class="rp-action" href="{{ route('portal.account') }}">
                <span class="rp-action-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="12" height="17" rx="2"/><path d="M9 4V3h6v1M9 10h6M9 14h6M9 18h3"/></svg></span>
                <span class="rp-action-title"><span data-portal-i18n="My requests">{{ __('My requests') }}</span></span>
                <span class="rp-action-text"><span data-portal-i18n="Tingnan ang status ng mga na-submit mo.">{{ __('Tingnan ang status ng mga na-submit mo.') }}</span></span>
            </a>
        </nav>

        <section class="rp-panel" aria-labelledby="summary-title">
            <h2 id="summary-title"><span data-portal-i18n="Buod ng mga request">{{ __('Buod ng mga request') }}</span></h2>
            <ul class="rp-stats">
                @foreach($summary as [$label, $count, $status])
                    <li data-status="{{ $status }}"><span class="rp-stat-number">{{ $count }}</span><span class="rp-stat-label"><span data-portal-i18n="{{ $label }}">{{ __($label) }}</span></span></li>
                @endforeach
            </ul>
        </section>

        <section class="rp-panel" aria-labelledby="latest-title">
            <div class="rp-panel-head">
                <h2 id="latest-title"><span data-portal-i18n="Mga huling request">{{ __('Mga huling request') }}</span></h2>
                @if($latestRequests->isNotEmpty())<a href="{{ route('portal.account') }}"><span data-portal-i18n="Tingnan lahat">{{ __('Tingnan lahat') }}</span></a>@endif
            </div>
            @if($latestRequests->isEmpty())
                <div class="rp-empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h3"/></svg>
                    <p><strong><span data-portal-i18n="Wala pang request">{{ __('Wala pang request') }}</span></strong></p>
                    <p><span data-portal-i18n="Pagka-submit ng request, dito makikita ang reference number, status, at kung puwede na itong kunin sa Barangay Hall.">{{ __('Pagka-submit ng request, dito makikita ang reference number, status, at kung puwede na itong kunin sa Barangay Hall.') }}</span></p>
                </div>
            @else
                <ul class="rp-mini-list">
                    @foreach($latestRequests as $item)
                        <li>
                            <a href="{{ route('portal.account.requests.show', $item->reference_code) }}">
                                <span class="rp-mini-doc"><strong><span data-portal-i18n="{{ $item->document_type }}">{{ __($item->document_type) }}</span></strong><span class="v2-ref">{{ $item->reference_code }}</span></span>
                                @include('portal.partials.request-status-pill', ['status' => $item->status])
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <aside class="rp-dashboard-side">
        <section class="rp-panel rp-profile-card" aria-labelledby="account-name">
            @if($resident->has_photo)
                <img class="v2-avatar" src="{{ route('portal.profile.photo') }}" width="72" height="72" alt="">
            @else
                <span class="v2-avatar" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span>
            @endif
            <h2 id="account-name">{{ $resident->full_name }}</h2>
            <p class="v2-summary-note"><span data-portal-i18n="Resident number">{{ __('Resident number') }}</span> <span class="v2-ref">{{ $resident->resident_number }}</span></p>
            @unless($resident->has_photo)
                <p class="rp-note-warning"><span data-portal-i18n="Wala pang resident photo. Mag-upload ng malinaw na larawan ng inyong mukha.">{{ __('Wala pang resident photo. Mag-upload ng malinaw na larawan ng inyong mukha.') }}</span></p>
            @endunless
            <ul class="v2-links v2-links-icon">
                <li><a href="{{ route('portal.profile') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg><span data-portal-i18n="Aking profile">{{ __('Aking profile') }}</span></a></li>
                <li><a href="{{ route('password.request', ['from' => 'portal']) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg><span data-portal-i18n="Palitan ang password">{{ __('Palitan ang password') }}</span></a></li>
                <li><a href="{{ route('portal.information') }}#help"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 0 1 5 0c0 2-2.5 2-2.5 4M12 17h.01"/></svg><span data-portal-i18n="Get help">{{ __('Get help') }}</span></a></li>
            </ul>
            <button type="button" class="btn btn-outline rp-logout" data-resident-logout><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 4H5v16h5M14 8l4 4-4 4M18 12H9"/></svg><span data-portal-i18n="Log out">{{ __('Log out') }}</span></button>
        </section>
        <section class="rp-panel rp-panel-info" aria-labelledby="visit-title">
            <h2 id="visit-title"><span data-portal-i18n="Bago pumunta sa Barangay Hall">{{ __('Bago pumunta sa Barangay Hall') }}</span></h2>
            <ul class="v2-list">
                <li><span data-portal-i18n="Hintaying maging Ready for release ang request bago pumunta.">{{ __('Hintaying maging Ready for release ang request bago pumunta.') }}</span></li>
                <li><span data-portal-i18n="Dalhin ang original na valid ID at ang reference number.">{{ __('Dalhin ang original na valid ID at ang reference number.') }}</span></li>
                <li><span data-portal-i18n="Sa Barangay Hall babayaran ang fee, kung mayroon. Walang online payment.">{{ __('Sa Barangay Hall babayaran ang fee, kung mayroon. Walang online payment.') }}</span></li>
            </ul>
        </section>
    </aside>
</div>
@endsection
