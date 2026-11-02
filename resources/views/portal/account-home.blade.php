@extends('layouts.portal')
@section('title', __('Aking account'))
@section('band')
<x-portal.page-band title="Aking account">
    <p><span data-portal-i18n="Ang inyong impormasyon bilang residente at ang mga setting ng account.">{{ __('Ang inyong impormasyon bilang residente at ang mga setting ng account.') }}</span></p>
</x-portal.page-band>
@endsection
@section('content')
<div class="rp-account-page" data-resident-private>
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
        <p class="v2-summary-note"><span data-portal-i18n="Hindi mae-edit online ang resident record. Para magpa-correct, pumunta sa Barangay Hall at dalhin ang valid ID.">{{ __('Hindi mae-edit online ang resident record. Para magpa-correct, pumunta sa Barangay Hall at dalhin ang valid ID.') }}</span></p>
        <a class="btn btn-outline btn-full" href="{{ route('portal.profile') }}"><span data-portal-i18n="Pamahalaan ang profile at photo">{{ __('Pamahalaan ang profile at photo') }}</span></a>
    </section>
    <section class="rp-panel" aria-labelledby="account-actions-title">
        <h2 id="account-actions-title"><span data-portal-i18n="Seguridad ng account">{{ __('Seguridad ng account') }}</span></h2>
        <p class="v2-summary-note"><span data-portal-i18n="Padadalhan kayo ng link sa email ng inyong account para gumawa ng bagong password.">{{ __('Padadalhan kayo ng link sa email ng inyong account para gumawa ng bagong password.') }}</span></p>
        <div class="rp-account-actions">
            <a class="btn btn-outline" href="{{ route('password.request', ['from' => 'portal']) }}"><span data-portal-i18n="Palitan ang password">{{ __('Palitan ang password') }}</span></a>
            <button type="button" class="btn btn-outline rp-logout" data-resident-logout><span data-portal-i18n="Log out">{{ __('Log out') }}</span></button>
        </div>
    </section>
</div>
@endsection
