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
<x-portal.page-band title="Aking account">
    <p><span data-portal-i18n="Magandang araw, :name." data-portal-params="{{ json_encode(['name' => $resident->first_name]) }}">{{ __('Magandang araw, :name.', ['name' => $resident->first_name]) }}</span> <span data-portal-i18n="Narito ang buod ng inyong mga request at account.">{{ __('Narito ang buod ng inyong mga request at account.') }}</span></p>
    <x-slot:actions>
        <a class="btn btn-green" href="{{ route('portal.request.create') }}"><span aria-hidden="true">+</span> <span data-portal-i18n="Request a document">{{ __('Request a document') }}</span></a>
    </x-slot:actions>
</x-portal.page-band>
@endsection
@section('content')
<div class="rp-dashboard" data-resident-private>
    <div class="rp-dashboard-main">
        @if($readyCount > 0)
            <a class="rp-alert-ready" href="{{ route('portal.account') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="currentColor"/><path d="M7 12.5l3.2 3.2L17 9" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span><strong><span data-portal-i18n="May dokumentong puwede nang kunin.">{{ __('May dokumentong puwede nang kunin.') }}</span></strong> <span data-portal-i18n="Dalhin ang original na valid ID at ang reference number.">{{ __('Dalhin ang original na valid ID at ang reference number.') }}</span></span>
            </a>
        @endif

        <section class="rp-panel" aria-labelledby="requests-title">
            <div class="rp-panel-head">
                <h2 id="requests-title"><span data-portal-i18n="Mga request">{{ __('Mga request') }}</span></h2>
                @if($latestRequests->isNotEmpty())<a href="{{ route('portal.account') }}"><span data-portal-i18n="Tingnan ang lahat ng request">{{ __('Tingnan ang lahat ng request') }}</span></a>@endif
            </div>
            <ul class="rp-stats">
                @foreach($summary as [$label, $count, $status])
                    <li data-status="{{ $status }}"><span class="rp-stat-number">{{ $count }}</span><span class="rp-stat-label"><span data-portal-i18n="{{ $label }}">{{ __($label) }}</span></span></li>
                @endforeach
            </ul>
            <h3 class="rp-subhead"><span data-portal-i18n="Mga huling request">{{ __('Mga huling request') }}</span></h3>
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
                                <span class="rp-mini-date"><time datetime="{{ $item->created_at->toDateString() }}">{{ $item->created_at->format('M j, Y') }}</time></span>
                                @include('portal.partials.request-status-pill', ['status' => $item->status])
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <aside class="rp-dashboard-side">
        <section class="rp-panel rp-profile-card" aria-labelledby="resident-info-title">
            <h2 id="resident-info-title"><span data-portal-i18n="Impormasyon ng residente">{{ __('Impormasyon ng residente') }}</span></h2>
            <div class="rp-profile-head">
                @if($resident->has_photo)
                    <img class="v2-avatar" src="{{ route('portal.profile.photo') }}" width="64" height="64" alt="">
                @else
                    <span class="v2-avatar" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span>
                @endif
                <div><strong>{{ $resident->full_name }}</strong><span class="v2-ref">{{ $resident->resident_number }}</span></div>
            </div>
            <dl class="rp-info-list">
                <div><dt><span data-portal-i18n="Address">{{ __('Address') }}</span></dt><dd>{{ $resident->address }}</dd></div>
                <div><dt><span data-portal-i18n="Account email">{{ __('Account email') }}</span></dt><dd>{{ auth('resident')->user()->email }}</dd></div>
            </dl>
            @unless($resident->has_photo)
                <p class="rp-note-warning"><span data-portal-i18n="Wala pang resident photo. Mag-upload ng malinaw na larawan ng inyong mukha.">{{ __('Wala pang resident photo. Mag-upload ng malinaw na larawan ng inyong mukha.') }}</span></p>
            @endunless
            <a class="btn btn-outline btn-full" href="{{ route('portal.profile') }}"><span data-portal-i18n="Pamahalaan ang profile at photo">{{ __('Pamahalaan ang profile at photo') }}</span></a>
        </section>
        <section class="rp-panel" aria-labelledby="account-actions-title">
            <h2 id="account-actions-title"><span data-portal-i18n="Account">{{ __('Account') }}</span></h2>
            <div class="rp-account-actions">
                <a class="btn btn-outline" href="{{ route('password.request', ['from' => 'portal']) }}"><span data-portal-i18n="Palitan ang password">{{ __('Palitan ang password') }}</span></a>
                <button type="button" class="btn btn-outline rp-logout" data-resident-logout><span data-portal-i18n="Log out">{{ __('Log out') }}</span></button>
            </div>
        </section>
    </aside>
</div>
@endsection
