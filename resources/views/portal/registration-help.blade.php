@extends('layouts.portal')
@section('title', __('Registration help'))
@section('content')
<x-portal.auth-shell title="Hindi natapos ang registration" title-id="registration-help-title" back="Create account" :back-url="route('portal.register')">
<section class="card rp-form-card" aria-labelledby="registration-help-title">
    <div class="card-body stack">
        @if(session('existing_account'))
            <p class="alert alert-blue"><span data-portal-i18n="May online account na para sa resident record na ito.">{{ __('May online account na para sa resident record na ito.') }}</span></p>
            <p><span data-portal-i18n="Mag-log in gamit ang email ng account na iyon. Kung hindi na ninyo ito mabuksan, i-reset ang password o magpatulong sa Barangay Hall.">{{ __('Mag-log in gamit ang email ng account na iyon. Kung hindi na ninyo ito mabuksan, i-reset ang password o magpatulong sa Barangay Hall.') }}</span></p>
            <div class="btn-row"><a class="btn btn-green" href="{{ route('portal.login') }}"><span data-portal-i18n="Log in">{{ __('Log in') }}</span></a><a class="btn btn-outline" href="{{ route('password.request', ['from' => 'portal']) }}"><span data-portal-i18n="Reset password">{{ __('Reset password') }}</span></a></div>
        @else
            <p><span data-portal-i18n="Hindi magamit ang Resident Number at Activation Code na inilagay ninyo. Posibleng dahilan:">{{ __('Hindi magamit ang Resident Number at Activation Code na inilagay ninyo. Posibleng dahilan:') }}</span></p>
            <ul class="plain-list">
                <li><span data-portal-i18n="Mali ang pagkaka-type ng Resident Number o ng code.">{{ __('Mali ang pagkaka-type ng Resident Number o ng code.') }}</span></li>
                <li><span data-portal-i18n="Lumipas na ang 24 oras, nagamit na ang code, o napalitan na ito ng bagong code.">{{ __('Lumipas na ang 24 oras, nagamit na ang code, o napalitan na ito ng bagong code.') }}</span></li>
                <li><span data-portal-i18n="Masyadong maraming maling subok, kaya hindi na gumagana ang code.">{{ __('Masyadong maraming maling subok, kaya hindi na gumagana ang code.') }}</span></li>
            </ul>
            <p><a class="btn btn-green" href="{{ route('portal.new-resident.create') }}"><span data-portal-i18n="Apply as a new resident">{{ __('Apply as a new resident') }}</span></a></p>
            <h2 class="fieldset-title"><span data-portal-i18n="Ano ang gagawin">{{ __('Ano ang gagawin') }}</span></h2>
            <p><span data-portal-i18n="Pumunta sa Barangay Anabu I-G Hall at magdala ng valid ID. Ive-verify ng staff ang identity ninyo at bibigyan kayo ng bagong activation code. Kung wala pa kayong record, puwede kayong mag-apply bilang bagong residente.">{{ __('Pumunta sa Barangay Anabu I-G Hall at magdala ng valid ID. Ive-verify ng staff ang identity ninyo at bibigyan kayo ng bagong activation code. Kung wala pa kayong record, puwede kayong mag-apply bilang bagong residente.') }}</span></p>
            <div class="btn-row"><a class="btn btn-green" href="{{ route('portal.register') }}"><span data-portal-i18n="Subukan ulit">{{ __('Subukan ulit') }}</span></a><a class="btn btn-outline" href="{{ route('portal.information') }}#help"><span data-portal-i18n="Get help">{{ __('Get help') }}</span></a></div>
            <p class="auth-links"><span data-portal-i18n="May account na?">{{ __('May account na?') }}</span> <a href="{{ route('portal.login') }}"><span data-portal-i18n="Log in">{{ __('Log in') }}</span></a> &middot; <a href="{{ route('password.request', ['from' => 'portal']) }}"><span data-portal-i18n="Nakalimutan ang password?">{{ __('Nakalimutan ang password?') }}</span></a></p>
        @endif
    </div>
</section>
</x-portal.auth-shell>
@endsection
