@extends('layouts.portal')
@section('title', __('Log in'))
@section('band')
<x-portal.page-band title="Welcome back" title-id="login-title">
    <p><span data-portal-i18n="Mag-log in gamit ang inyong verified resident account.">{{ __('Mag-log in gamit ang inyong verified resident account.') }}</span></p>
</x-portal.page-band>
@endsection
@section('content')
<div class="auth-layout">
    <section class="card card-accent" aria-labelledby="resident-login-card-title">
        <form class="card-body" method="POST" action="{{ route('portal.login.store') }}" data-resident-form>
            @csrf
            <div class="resident-auth-heading">
                <span class="resident-auth-symbol" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/></svg></span>
                <div><h2 id="resident-login-card-title"><span data-portal-i18n="Resident account login">{{ __('Resident account login') }}</span></h2><p><span data-portal-i18n="Protected access to your document requests and resident record.">{{ __('Protected access to your document requests and resident record.') }}</span></p></div>
            </div>
            <div class="form-group">
                <label class="form-label" for="resident-email"><span data-portal-i18n="Email address">{{ __('Email address') }}</span></label>
                <input class="form-input" id="resident-email" name="email" type="email" value="{{ old('email') }}" data-portal-i18n-placeholder="juan.delacruz@email.com" placeholder="{{ __('juan.delacruz@email.com') }}" autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" required maxlength="255" @error('email') aria-invalid="true" aria-describedby="resident-email-error" @enderror>
                @error('email')<p class="field-error" id="resident-email-error" data-portal-message>{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="resident-password"><span data-portal-i18n="Password">{{ __('Password') }}</span></label>
                <span class="resident-password-field"><input class="form-input" id="resident-password" name="password" type="password" autocomplete="current-password" required><button class="resident-password-toggle" type="button" data-password-toggle="resident-password" aria-controls="resident-password" aria-pressed="false" data-portal-i18n-aria-label="Show password" aria-label="{{ __('Show password') }}" data-portal-i18n-title="Show password" title="{{ __('Show password') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="password-eye-slash" d="m3 3 18 18"/></svg></button></span>
            </div>
            <label class="resident-remember"><input name="remember" type="checkbox" value="1"> <span data-portal-i18n="Manatiling naka-log in sa device na ito. Huwag gamitin sa shared o pampublikong computer.">{{ __('Manatiling naka-log in sa device na ito. Huwag gamitin sa shared o pampublikong computer.') }}</span></label>
            <button class="btn btn-green btn-full" type="submit"><span data-portal-i18n="Log in securely">{{ __('Log in securely') }}</span> <span aria-hidden="true">&#8594;</span></button>
            <p class="auth-links"><a href="{{ route('password.request') }}"><span data-portal-i18n="Nakalimutan ang password?">{{ __('Nakalimutan ang password?') }}</span></a></p>
            <p class="resident-auth-security"><span data-portal-i18n="Secure at encrypted ang inyong login information.">{{ __('Secure at encrypted ang inyong login information.') }}</span></p>
        </form>
    </section>
    <aside class="auth-aside" data-portal-i18n-aria-label="Tungkol sa resident account" aria-label="{{ __('Tungkol sa resident account') }}">
        <section>
            <h2><span data-portal-i18n="Wala pang account?">{{ __('Wala pang account?') }}</span></h2>
            <p><span data-portal-i18n="Para makagawa ng account, kailangang nasa Resident Records na kayo ng Barangay Anabu I-G. Hahanapin ang record ninyo gamit ang:">{{ __('Para makagawa ng account, kailangang nasa Resident Records na kayo ng Barangay Anabu I-G. Hahanapin ang record ninyo gamit ang:') }}</span></p>
            <ul class="plain-list">
                <li><span data-portal-i18n="buong pangalan">{{ __('buong pangalan') }}</span></li>
                <li><span data-portal-i18n="petsa ng kapanganakan">{{ __('petsa ng kapanganakan') }}</span></li>
                <li><span data-portal-i18n="huling 4 na digit ng contact number na nasa record">{{ __('huling 4 na digit ng contact number na nasa record') }}</span></li>
            </ul>
            <p class="auth-links"><a class="btn btn-outline btn-full" href="{{ route('portal.register') }}"><span data-portal-i18n="Create resident account">{{ __('Create resident account') }}</span> <span aria-hidden="true">&#8594;</span></a></p>
        </section>
        <section>
            <h2><span data-portal-i18n="Pagka-log in">{{ __('Pagka-log in') }}</span></h2>
            <p><span data-portal-i18n="Puwede nang mag-request ng dokumento at makita ang status nito sa My requests.">{{ __('Puwede nang mag-request ng dokumento at makita ang status nito sa My requests.') }}</span></p>
        </section>
        <section>
            <h2><span data-portal-i18n="Hindi makapag-log in?">{{ __('Hindi makapag-log in?') }}</span></h2>
            <p><span data-portal-i18n="Kung hindi gumagana ang account o may maling detalye sa record, pumunta sa Barangay Hall para magpatulong.">{{ __('Kung hindi gumagana ang account o may maling detalye sa record, pumunta sa Barangay Hall para magpatulong.') }}</span> <a href="{{ route('portal.information') }}#help"><span data-portal-i18n="Get help">{{ __('Get help') }}</span></a></p>
        </section>
    </aside>
</div>
@endsection
