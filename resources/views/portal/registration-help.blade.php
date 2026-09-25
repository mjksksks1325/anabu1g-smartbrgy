@extends('layouts.portal')
@section('title', 'Registration help')
@section('band')
<x-portal.page-band title="Hindi natapos ang registration" title-id="registration-help-title" parent="Create account" :parent-url="route('portal.register')" />
@endsection
@section('content')
<section class="card card-accent narrow" aria-labelledby="registration-help-title">
    <div class="card-body stack">
        @if(session('existing_account'))
            <p class="alert alert-blue">May online account na para sa resident record na ito.</p>
            <p>Mag-log in gamit ang email ng account na iyon. Kung hindi na ninyo ito mabuksan, i-reset ang password o magpatulong sa Barangay Hall.</p>
            <div class="btn-row"><a class="btn btn-green" href="{{ route('portal.login') }}">Log in</a><a class="btn btn-outline" href="{{ route('password.request') }}">Reset password</a></div>
        @else
            <p>Hindi ma-verify ang impormasyon ninyo sa kasalukuyang Resident Records ng Barangay Anabu I-G. Posibleng dahilan:</p>
            <ul class="plain-list">
                <li>Bagong lipat kayo at wala pa ang record ninyo sa database.</li>
                <li>Iba ang pagkakasulat ng pangalan o contact number sa barangay record.</li>
                <li>Kailangan pang i-verify o i-update ng barangay staff ang record ninyo.</li>
            </ul>
            <h2 class="fieldset-title">Ano ang gagawin</h2>
            <p>Pumunta sa Barangay Anabu I-G Hall para magpa-register, magpa-verify, o magpa-update ng Resident Record. Dalhin ang valid ID at itanong sa staff kung may iba pang kailangang patunay ng paninirahan. Puwede rin kayong bigyan ng staff ng activation code matapos i-verify ang identity ninyo.</p>
            <div class="btn-row"><a class="btn btn-green" href="{{ route('portal.register') }}">Subukan ulit</a><a class="btn btn-outline" href="{{ route('portal.information') }}#help">Get help</a></div>
            <p class="auth-links">May account na? <a href="{{ route('portal.login') }}">Log in</a> &middot; <a href="{{ route('password.request') }}">Nakalimutan ang password?</a></p>
        @endif
    </div>
</section>
@endsection
