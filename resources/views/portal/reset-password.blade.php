@extends('layouts.portal')
@section('title', __('Reset password'))
@section('content')
<x-portal.auth-shell title="Reset password" title-id="reset-title" lead="Create a new password for your resident account." back="Log in" :back-url="route('portal.login')">
    <section class="card rp-form-card" aria-labelledby="reset-title">
        <form class="card-body" method="POST" action="{{ route('password.update') }}" data-resident-form>
            @csrf
            <input type="hidden" name="from" value="portal">
            <input type="hidden" name="token" value="{{ request()->route('token') }}">
            <div class="form-group">
                <label class="form-label" for="reset-email"><span data-portal-i18n="Email address">{{ __('Email address') }}</span></label>
                <input class="form-input" id="reset-email" name="email" type="email" value="{{ old('email', request('email')) }}" required maxlength="255" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" @error('email') aria-invalid="true" aria-describedby="reset-email-error" @enderror>
                @error('email')<p class="field-error" id="reset-email-error" data-portal-message>{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="reset-password"><span data-portal-i18n="Password">{{ __('Password') }}</span></label>
                <span class="password-visibility-field resident-password-field"><input class="form-input" id="reset-password" name="password" type="password" required minlength="12" maxlength="255" autocomplete="new-password" aria-describedby="reset-password-help" @error('password') aria-invalid="true" @enderror><x-password-toggle target="reset-password" :resident="true" /></span>
                @error('password')<p class="field-error" data-portal-message>{{ $message }}</p>@enderror
                <p id="reset-password-help" class="form-note"><span data-portal-i18n="Gumawa ng bagong password: hindi bababa sa 12 characters, may uppercase at lowercase letter, number, at simbolo (halimbawa: ! o #).">{{ __('Gumawa ng bagong password: hindi bababa sa 12 characters, may uppercase at lowercase letter, number, at simbolo (halimbawa: ! o #).') }}</span></p>
            </div>
            <div class="form-group">
                <label class="form-label" for="reset-confirmation"><span data-portal-i18n="Ulitin ang password">{{ __('Ulitin ang password') }}</span></label>
                <span class="password-visibility-field resident-password-field"><input class="form-input" id="reset-confirmation" name="password_confirmation" type="password" required autocomplete="new-password"><x-password-toggle target="reset-confirmation" :resident="true" /></span>
            </div>
            <button class="btn btn-green btn-full" type="submit"><span data-portal-i18n="Reset password">{{ __('Reset password') }}</span></button>
        </form>
    </section>
    <x-slot:help>
    <section class="rp-help-sections" aria-labelledby="reset-next-title">
        <h2 id="reset-next-title"><span data-portal-i18n="Ano ang susunod?">{{ __('Ano ang susunod?') }}</span></h2>
        <ol class="v2-list">
            <li><span data-portal-i18n="Bumalik sa Resident Portal at mag-log in gamit ang bagong password.">{{ __('Bumalik sa Resident Portal at mag-log in gamit ang bagong password.') }}</span></li>
        </ol>
        <p class="v2-summary-note"><span data-portal-i18n="The link works for 60 minutes. If it has expired, request a new one.">{{ __('The link works for 60 minutes. If it has expired, request a new one.') }}</span> <a href="{{ route('password.request', ['from' => 'portal']) }}"><span data-portal-i18n="Request a new reset link">{{ __('Request a new reset link') }}</span></a></p>
    </section>
    </x-slot:help>
</x-portal.auth-shell>
@endsection
