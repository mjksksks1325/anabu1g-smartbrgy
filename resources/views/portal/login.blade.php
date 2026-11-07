@extends('layouts.portal')
@section('title', __('Log in'))
@section('content')
<x-portal.auth-shell title="Log in" title-id="login-title" lead="Mag-log in gamit ang inyong verified resident account.">
    <section class="card rp-form-card" aria-labelledby="login-title">
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
    <x-slot:help>
        <section class="rp-paths" data-portal-i18n-aria-label="Tungkol sa resident account" aria-label="{{ __('Tungkol sa resident account') }}">
            <h2><span data-portal-i18n="Wala pang account?">{{ __('Wala pang account?') }}</span></h2>
            <a class="rp-path" href="{{ route('portal.register') }}">
                <span class="rp-path-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="10" cy="8" r="4"/><path d="M3 21a7 7 0 0 1 14 0M19 8v6M16 11h6"/></svg></span>
                <span><strong><span data-portal-i18n="Nasa barangay record na kayo">{{ __('Nasa barangay record na kayo') }}</span></strong><span data-portal-i18n="Ilagay ang Resident Number at Activation Code na ibinigay ng staff matapos i-verify ang identity ninyo.">{{ __('Ilagay ang Resident Number at Activation Code na ibinigay ng staff matapos i-verify ang identity ninyo.') }}</span><span class="rp-path-action"><span data-portal-i18n="Create resident account">{{ __('Create resident account') }}</span></span></span>
            </a>
            <a class="rp-path" href="{{ route('portal.new-resident.create') }}">
                <span class="rp-path-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V10l7-5 7 5v11"/><path d="M12 11v6M9 14h6"/></svg></span>
                <span><strong><span data-portal-i18n="No resident record yet?">{{ __('No resident record yet?') }}</span></strong><span data-portal-i18n="Submit your details for barangay review.">{{ __('Submit your details for barangay review.') }}</span><span class="rp-path-action"><span data-portal-i18n="Apply as a new resident">{{ __('Apply as a new resident') }}</span></span></span>
            </a>
            <p class="v2-summary-note"><span data-portal-i18n="Hindi makapag-log in?">{{ __('Hindi makapag-log in?') }}</span> <a href="{{ route('portal.information') }}#help"><span data-portal-i18n="Get help">{{ __('Get help') }}</span></a></p>
        </section>
    </x-slot:help>
</x-portal.auth-shell>
@endsection
