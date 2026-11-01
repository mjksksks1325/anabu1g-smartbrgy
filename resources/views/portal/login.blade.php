@extends('layouts.portal')
@section('title', __('Log in'))
@section('band')
<x-portal.page-band title="Log in" title-id="login-title">
    <p><span data-portal-i18n="Mag-log in gamit ang inyong verified resident account.">{{ __('Mag-log in gamit ang inyong verified resident account.') }}</span></p>
</x-portal.page-band>
@endsection
@section('content')
<div class="auth-layout v2-form-page">
    <section class="card v2-form-card" aria-labelledby="login-title">
        <form class="card-body" method="POST" action="{{ route('portal.login.store') }}" data-resident-form>
            @csrf
            <div class="form-group">
                <label class="form-label" for="resident-email"><span data-portal-i18n="Email address">{{ __('Email address') }}</span></label>
                <input class="form-input" id="resident-email" name="email" type="email" value="{{ old('email') }}" data-portal-i18n-placeholder="juan.delacruz@email.com" placeholder="{{ __('juan.delacruz@email.com') }}" autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" required maxlength="255" @error('email') aria-invalid="true" aria-describedby="resident-email-error" @enderror>
                @error('email')<p class="field-error" id="resident-email-error" data-portal-message>{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <div class="v2-label-row">
                    <label class="form-label" for="resident-password"><span data-portal-i18n="Password">{{ __('Password') }}</span></label>
                    <a href="{{ route('password.request', ['from' => 'portal']) }}"><span data-portal-i18n="Nakalimutan ang password?">{{ __('Nakalimutan ang password?') }}</span></a>
                </div>
                <span class="password-visibility-field resident-password-field"><input class="form-input" id="resident-password" name="password" type="password" autocomplete="current-password" required><x-password-toggle target="resident-password" :resident="true" /></span>
            </div>
            <label class="resident-remember"><input name="remember" type="checkbox" value="1"> <span data-portal-i18n="Manatiling naka-log in sa device na ito. Huwag gamitin sa shared o pampublikong computer.">{{ __('Manatiling naka-log in sa device na ito. Huwag gamitin sa shared o pampublikong computer.') }}</span></label>
            <button class="btn btn-green btn-full" type="submit"><span data-portal-i18n="Log in securely">{{ __('Log in securely') }}</span></button>
            <p class="v2-secure-note"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg><span data-portal-i18n="Secure at encrypted ang inyong login information.">{{ __('Secure at encrypted ang inyong login information.') }}</span></p>
        </form>
    </section>
    <aside class="v2-card v2-side-panel" data-portal-i18n-aria-label="Tungkol sa resident account" aria-label="{{ __('Tungkol sa resident account') }}">
        <h2><span data-portal-i18n="Wala pang account?">{{ __('Wala pang account?') }}</span></h2>
        <div class="v2-path">
            <h3><span data-portal-i18n="Nasa barangay record na kayo">{{ __('Nasa barangay record na kayo') }}</span></h3>
            <p><span data-portal-i18n="Hahanapin ang record ninyo gamit ang buong pangalan at petsa ng kapanganakan, saka kayo gagawa ng password.">{{ __('Hahanapin ang record ninyo gamit ang buong pangalan at petsa ng kapanganakan, saka kayo gagawa ng password.') }}</span></p>
            <a class="btn btn-outline btn-full" href="{{ route('portal.register') }}"><span data-portal-i18n="Create resident account">{{ __('Create resident account') }}</span></a>
        </div>
        <div class="v2-path">
            <h3><span data-portal-i18n="No resident record yet?">{{ __('No resident record yet?') }}</span></h3>
            <p><span data-portal-i18n="Submit your details for barangay review.">{{ __('Submit your details for barangay review.') }}</span></p>
            <a href="{{ route('portal.new-resident.create') }}"><span data-portal-i18n="Apply as a new resident">{{ __('Apply as a new resident') }}</span></a>
        </div>
        <p class="v2-summary-note"><span data-portal-i18n="Hindi makapag-log in?">{{ __('Hindi makapag-log in?') }}</span> <a href="{{ route('portal.information') }}#help"><span data-portal-i18n="Get help">{{ __('Get help') }}</span></a></p>
    </aside>
</div>
@endsection
