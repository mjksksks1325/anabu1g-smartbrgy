@extends('layouts.portal')
@section('title', __('Reset password'))
@section('band')
<x-portal.page-band title="Reset password" title-id="forgot-title" parent="Log in" :parent-url="route('portal.login')">
    <p><span data-portal-i18n="Ilagay ang email ng inyong resident account. Padadalhan namin kayo ng link para gumawa ng bagong password.">{{ __('Ilagay ang email ng inyong resident account. Padadalhan namin kayo ng link para gumawa ng bagong password.') }}</span></p>
</x-portal.page-band>
@endsection
@section('content')
<div class="auth-layout v2-form-page">
    <section class="card v2-form-card" aria-labelledby="forgot-title">
        <form class="card-body" method="POST" action="{{ route('password.email') }}" data-resident-form>
            @csrf
            <div class="form-group">
                <label class="form-label" for="reset-email"><span data-portal-i18n="Email address">{{ __('Email address') }}</span></label>
                <input class="form-input" id="reset-email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" @error('email') aria-invalid="true" aria-describedby="reset-email-error" @enderror>
                @error('email')<p class="field-error" id="reset-email-error" data-portal-message>{{ $message }}</p>@enderror
            </div>
            <button class="btn btn-green btn-full" type="submit"><span data-portal-i18n="Ipadala ang reset link">{{ __('Ipadala ang reset link') }}</span></button>
        </form>
    </section>
    <aside class="v2-card v2-side-panel" aria-labelledby="forgot-next-title">
        <h2 id="forgot-next-title"><span data-portal-i18n="Ano ang susunod?">{{ __('Ano ang susunod?') }}</span></h2>
        <ol class="v2-list">
            <li><span data-portal-i18n="Buksan ang email at pindutin ang reset link.">{{ __('Buksan ang email at pindutin ang reset link.') }}</span></li>
            <li><span data-portal-i18n="Gumawa ng bagong password: hindi bababa sa 12 characters, may uppercase at lowercase letter, number, at simbolo (halimbawa: ! o #).">{{ __('Gumawa ng bagong password: hindi bababa sa 12 characters, may uppercase at lowercase letter, number, at simbolo (halimbawa: ! o #).') }}</span></li>
            <li><span data-portal-i18n="Bumalik sa Resident Portal at mag-log in gamit ang bagong password.">{{ __('Bumalik sa Resident Portal at mag-log in gamit ang bagong password.') }}</span></li>
        </ol>
        <p class="v2-summary-note"><span data-portal-i18n="Walang dumating? Tingnan ang Spam folder. Kung hindi na mabuksan ang email, magpatulong sa Barangay Hall.">{{ __('Walang dumating? Tingnan ang Spam folder. Kung hindi na mabuksan ang email, magpatulong sa Barangay Hall.') }}</span></p>
        <a href="{{ route('portal.login') }}"><span data-portal-i18n="Bumalik sa Log in">{{ __('Bumalik sa Log in') }}</span></a>
    </aside>
</div>
@endsection
