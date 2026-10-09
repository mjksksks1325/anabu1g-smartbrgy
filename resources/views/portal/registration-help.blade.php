@extends('layouts.portal')
@section('title', __('Registration help'))
@section('band')
<x-portal.page-band title="Hindi natapos ang registration" title-id="registration-help-title" parent="Create account" :parent-url="route('portal.register')" />
@endsection
@section('content')
<section class="card card-accent narrow" aria-labelledby="registration-help-title">
    <div class="card-body stack">
        @if(session('existing_account'))
            <p class="alert alert-blue"><span data-portal-i18n="May online account na para sa resident record na ito.">{{ __('May online account na para sa resident record na ito.') }}</span></p>
            <p><span data-portal-i18n="Mag-log in gamit ang email ng account na iyon. Kung hindi na ninyo ito mabuksan, i-reset ang password o magpatulong sa Barangay Hall.">{{ __('Mag-log in gamit ang email ng account na iyon. Kung hindi na ninyo ito mabuksan, i-reset ang password o magpatulong sa Barangay Hall.') }}</span></p>
            <div class="btn-row"><a class="btn btn-green" href="{{ route('portal.login') }}"><span data-portal-i18n="Log in">{{ __('Log in') }}</span></a><a class="btn btn-outline" href="{{ route('password.request') }}"><span data-portal-i18n="Reset password">{{ __('Reset password') }}</span></a></div>
        @else
            <p><span data-portal-i18n="Hindi ma-verify ang impormasyon ninyo sa kasalukuyang Resident Records ng Barangay Anabu I-G. Posibleng dahilan:">{{ __('Hindi ma-verify ang impormasyon ninyo sa kasalukuyang Resident Records ng Barangay Anabu I-G. Posibleng dahilan:') }}</span></p>
            <ul class="plain-list">
                <li><span data-portal-i18n="Bagong lipat kayo at wala pa ang record ninyo sa database.">{{ __('Bagong lipat kayo at wala pa ang record ninyo sa database.') }}</span></li>
                <li><span data-portal-i18n="Iba ang pagkakasulat ng pangalan o petsa ng kapanganakan sa barangay record.">{{ __('Iba ang pagkakasulat ng pangalan o petsa ng kapanganakan sa barangay record.') }}</span></li>
                <li><span data-portal-i18n="Kailangan pang i-verify o i-update ng barangay staff ang record ninyo.">{{ __('Kailangan pang i-verify o i-update ng barangay staff ang record ninyo.') }}</span></li>
            </ul>
            <h2 class="fieldset-title"><span data-portal-i18n="Ano ang gagawin">{{ __('Ano ang gagawin') }}</span></h2>
            <p><span data-portal-i18n="Pumunta sa Barangay Anabu I-G Hall para magpa-register, magpa-verify, o magpa-update ng Resident Record. Dalhin ang valid ID at itanong sa staff kung may iba pang kailangang patunay ng paninirahan. Puwede rin kayong bigyan ng staff ng activation code matapos i-verify ang identity ninyo.">{{ __('Pumunta sa Barangay Anabu I-G Hall para magpa-register, magpa-verify, o magpa-update ng Resident Record. Dalhin ang valid ID at itanong sa staff kung may iba pang kailangang patunay ng paninirahan. Puwede rin kayong bigyan ng staff ng activation code matapos i-verify ang identity ninyo.') }}</span></p>
            <div class="btn-row"><a class="btn btn-green" href="{{ route('portal.register') }}"><span data-portal-i18n="Subukan ulit">{{ __('Subukan ulit') }}</span></a><a class="btn btn-outline" href="{{ route('portal.information') }}#help"><span data-portal-i18n="Get help">{{ __('Get help') }}</span></a></div>
            <p class="auth-links"><span data-portal-i18n="May account na?">{{ __('May account na?') }}</span> <a href="{{ route('portal.login') }}"><span data-portal-i18n="Log in">{{ __('Log in') }}</span></a> &middot; <a href="{{ route('password.request') }}"><span data-portal-i18n="Nakalimutan ang password?">{{ __('Nakalimutan ang password?') }}</span></a></p>
        @endif
    </div>
</section>
@endsection
