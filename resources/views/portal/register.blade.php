@extends('layouts.portal')
@section('title', __('Create account'))
@section('content')
@php
    // 1 eligibility (who can register online) · 2 the resident record is verified with the staff-issued code ·
    // 3 account · 4 email confirmation (after sign-up). There is no lookup by name or birth date: see ResidentRegistrationController.
    $stepNumber = match ($stage) { 'account' => 3, 'code' => 2, default => 1 };
    $stepLabels = ['Check eligibility', 'Verify resident record', 'Create account', 'Confirm email'];
@endphp
<x-portal.auth-shell title="Create your resident account" title-id="register-title" lead="Para sa mga residenteng nasa Resident Records na ng barangay.">
    <section class="card rp-form-card" aria-labelledby="register-title">
        <div class="card-body">
            <ol class="progress-steps" data-portal-i18n-aria-label="Mga hakbang sa pag-create ng account" aria-label="{{ __('Mga hakbang sa pag-create ng account') }}">
                @foreach($stepLabels as $index => $label)
                    <li @class(['done' => $index + 1 < $stepNumber]) @if($index + 1 === $stepNumber) aria-current="step" @endif><span><span data-portal-i18n="{{ $label }}">{{ __($label) }}</span></span></li>
                @endforeach
            </ol>
            <p class="step-count" data-portal-i18n="Step :current of :total" data-portal-params="{{ json_encode(['current' => $stepNumber, 'total' => 4]) }}">{{ __('Step :current of :total', ['current' => $stepNumber, 'total' => 4]) }}</p>

            @if($stage === 'account')
                <h2 class="registration-step-title"><span data-portal-i18n="Gumawa ng password">{{ __('Gumawa ng password') }}</span></h2>
                <p class="alert alert-green" role="status"><span data-portal-i18n="Tama ang activation code. Tapusin ang pag-create ng account sa loob ng 10 minuto.">{{ __('Tama ang activation code. Tapusin ang pag-create ng account sa loob ng 10 minuto.') }}</span></p>
                {{-- Shown only after the staff-issued code matched this record, so the resident can confirm it is theirs. --}}
                <dl class="registration-verified-record" data-verified-record>
                    <div><dt><span data-portal-i18n="Verified resident record">{{ __('Verified resident record') }}</span></dt><dd>{{ $verifiedResident->full_name }}</dd></div>
                    <div><dt><span data-portal-i18n="Resident number">{{ __('Resident number') }}</span></dt><dd>{{ $verifiedResident->resident_number }}</dd></div>
                </dl>
                <p class="form-note"><span data-portal-i18n="Not your record? Do not continue. Select Start over and ask the Barangay Hall for help.">{{ __('Not your record? Do not continue. Select Start over and ask the Barangay Hall for help.') }}</span></p>
                <form method="POST" action="{{ route('portal.register.store') }}" data-resident-form autocomplete="off">
                    @csrf
                    <div class="form-group">
                        <label class="form-label" for="registration-email"><span data-portal-i18n="Email address">{{ __('Email address') }}</span></label>
                        <input class="form-input" id="registration-email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" aria-describedby="registration-email-help" @error('email') aria-invalid="true" @enderror>
                        @error('email')<p class="field-error" data-portal-message>{{ $message }}</p>@enderror
                        <p class="form-note" id="registration-email-help"><span data-portal-i18n="Gumamit ng email na nabubuksan ninyo. Magpapadala kami ng link para kumpirmahin ito bago kayo makapag-request ng dokumento. Ito rin ang gagamitin sa pag-log in.">{{ __('Gumamit ng email na nabubuksan ninyo. Magpapadala kami ng link para kumpirmahin ito bago kayo makapag-request ng dokumento. Ito rin ang gagamitin sa pag-log in.') }}</span></p>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="registration-password"><span data-portal-i18n="Password">{{ __('Password') }}</span></label>
                        <span class="password-visibility-field resident-password-field"><input class="form-input" id="registration-password" name="password" type="password" required minlength="12" maxlength="255" autocomplete="new-password" aria-describedby="password-help" @error('password') aria-invalid="true" @enderror><x-password-toggle target="registration-password" :resident="true" /></span>
                        @error('password')<p class="field-error" data-portal-message>{{ $message }}</p>@enderror
                        <p id="password-help" class="form-note"><span data-portal-i18n="Hindi bababa sa 12 characters, may uppercase at lowercase letter, at may number.">{{ __('Hindi bababa sa 12 characters, may uppercase at lowercase letter, at may number.') }}</span></p>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="registration-confirmation"><span data-portal-i18n="Ulitin ang password">{{ __('Ulitin ang password') }}</span></label>
                        <span class="password-visibility-field resident-password-field"><input class="form-input" id="registration-confirmation" name="password_confirmation" type="password" required autocomplete="new-password"><x-password-toggle target="registration-confirmation" :resident="true" /></span>
                    </div>
                    <button class="btn btn-green btn-full" type="submit"><span data-portal-i18n="Create account">{{ __('Create account') }}</span></button>
                </form>
                <p class="next-note"><strong><span data-portal-i18n="Susunod:">{{ __('Susunod:') }}</span></strong> <span data-portal-i18n="buksan ang link sa email ninyo para kumpirmahin ito, saka mag-log in.">{{ __('buksan ang link sa email ninyo para kumpirmahin ito, saka mag-log in.') }}</span></p>
                <form method="POST" action="{{ route('portal.register.reset') }}" class="next-note">
                    @csrf
                    <button class="btn-link" type="submit"><span data-portal-i18n="Magsimula ulit">{{ __('Magsimula ulit') }}</span></button>
                </form>
            @elseif($stage === 'eligibility')
                <h2 class="registration-step-title"><span data-portal-i18n="Who can create an account online">{{ __('Who can create an account online') }}</span></h2>
                <p><span data-portal-i18n="Online registration is only for residents whom the barangay has already recorded and verified. Your account is linked to your barangay resident record, so the barangay confirms your identity first.">{{ __('Online registration is only for residents whom the barangay has already recorded and verified. Your account is linked to your barangay resident record, so the barangay confirms your identity first.') }}</span></p>
                <ol class="registration-eligibility" data-registration-eligibility>
                    <li>
                        <strong><span data-portal-i18n="You have a Resident Number and an Activation Code">{{ __('You have a Resident Number and an Activation Code') }}</span></strong>
                        <p><span data-portal-i18n="Barangay staff give these after checking your valid ID in person. Continue to verify your resident record.">{{ __('Barangay staff give these after checking your valid ID in person. Continue to verify your resident record.') }}</span></p>
                        <a class="btn btn-green" href="{{ route('portal.register', ['step' => 'verify']) }}"><span data-portal-i18n="Ilagay ang activation code">{{ __('Ilagay ang activation code') }}</span></a>
                    </li>
                    <li>
                        <strong><span data-portal-i18n="You are recorded but have no code yet">{{ __('You are recorded but have no code yet') }}</span></strong>
                        <p><span data-portal-i18n="Pumunta sa Barangay Hall at magdala ng valid ID. Ive-verify ng awtorisadong barangay staff ang identity ninyo.">{{ __('Pumunta sa Barangay Hall at magdala ng valid ID. Ive-verify ng awtorisadong barangay staff ang identity ninyo.') }}</span> <span data-portal-i18n="Ibibigay ng staff ang Resident Number at Activation Code ninyo. Isang beses lang ito magagamit at valid nang 24 oras.">{{ __('Ibibigay ng staff ang Resident Number at Activation Code ninyo. Isang beses lang ito magagamit at valid nang 24 oras.') }}</span></p>
                    </li>
                    <li>
                        <strong><span data-portal-i18n="No resident record yet?">{{ __('No resident record yet?') }}</span></strong>
                        <p><span data-portal-i18n="Apply as a new resident for barangay review, or register in person at the Barangay Hall with a valid ID.">{{ __('Apply as a new resident for barangay review, or register in person at the Barangay Hall with a valid ID.') }}</span></p>
                        <a class="btn btn-outline" href="{{ route('portal.new-resident.create') }}"><span data-portal-i18n="Apply as a new resident">{{ __('Apply as a new resident') }}</span></a>
                    </li>
                </ol>
            @else
                <h2 class="registration-step-title"><span data-portal-i18n="Verify resident record">{{ __('Verify resident record') }}</span></h2>
                <p><span data-portal-i18n="Enter the Resident Number and Activation Code from the barangay staff. They are checked against your barangay resident record.">{{ __('Enter the Resident Number and Activation Code from the barangay staff. They are checked against your barangay resident record.') }}</span></p>
                <div class="v2-callout v2-checklist">
                    <svg class="v2-callout-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6" y="4" width="12" height="17" rx="2"/><path d="M9 4V3h6v1M9 11l2 2 4-4M9 17h6"/></svg>
                    <div>
                        <h3><span data-portal-i18n="Kumuha muna ng activation code sa Barangay Hall">{{ __('Kumuha muna ng activation code sa Barangay Hall') }}</span></h3>
                        <ol class="v2-list">
                            <li><span data-portal-i18n="Pumunta sa Barangay Hall at magdala ng valid ID. Ive-verify ng awtorisadong barangay staff ang identity ninyo.">{{ __('Pumunta sa Barangay Hall at magdala ng valid ID. Ive-verify ng awtorisadong barangay staff ang identity ninyo.') }}</span></li>
                            <li><span data-portal-i18n="Ibibigay ng staff ang Resident Number at Activation Code ninyo. Isang beses lang ito magagamit at valid nang 24 oras.">{{ __('Ibibigay ng staff ang Resident Number at Activation Code ninyo. Isang beses lang ito magagamit at valid nang 24 oras.') }}</span></li>
                            <li><span data-portal-i18n="Ilagay ang dalawa sa ibaba, saka gumawa ng account.">{{ __('Ilagay ang dalawa sa ibaba, saka gumawa ng account.') }}</span></li>
                        </ol>
                    </div>
                </div>
                @include('portal.partials.activation-form')
                <p class="next-note"><strong><span data-portal-i18n="Susunod:">{{ __('Susunod:') }}</span></strong> <span data-portal-i18n="gagawa kayo ng password para sa account.">{{ __('gagawa kayo ng password para sa account.') }}</span></p>
                <div class="registration-path-option">
                    <div><strong><span data-portal-i18n="No resident record yet?">{{ __('No resident record yet?') }}</span></strong><p><span data-portal-i18n="Submit your details for barangay review.">{{ __('Submit your details for barangay review.') }}</span></p></div>
                    <a class="btn btn-outline" href="{{ route('portal.new-resident.create') }}"><span data-portal-i18n="Apply as a new resident">{{ __('Apply as a new resident') }}</span></a>
                </div>
            @endif
            <p class="auth-links"><span data-portal-i18n="May account na?">{{ __('May account na?') }}</span> <a href="{{ route('portal.login') }}"><span data-portal-i18n="Log in">{{ __('Log in') }}</span></a> &middot; <a href="{{ route('password.request', ['from' => 'portal']) }}"><span data-portal-i18n="Nakalimutan ang password?">{{ __('Nakalimutan ang password?') }}</span></a></p>
        </div>
    </section>
    <x-slot:help>
        <div class="rp-help-sections">
        <section>
            <h2><span data-portal-i18n="Bakit kailangang pumunta sa Barangay Hall?">{{ __('Bakit kailangang pumunta sa Barangay Hall?') }}</span></h2>
            <p><span data-portal-i18n="Para walang ibang makagamit ng resident record ninyo, ang barangay staff mismo ang nagbe-verify ng identity bago magbigay ng activation code.">{{ __('Para walang ibang makagamit ng resident record ninyo, ang barangay staff mismo ang nagbe-verify ng identity bago magbigay ng activation code.') }}</span></p>
        </section>
        <section>
            <h2><span data-portal-i18n="Data Privacy Notice">{{ __('Data Privacy Notice') }}</span></h2>
            <p><span data-portal-i18n="Gagamitin lamang ang inyong impormasyon para i-verify at gawin ang resident portal account.">{{ __('Gagamitin lamang ang inyong impormasyon para i-verify at gawin ang resident portal account.') }}</span></p>
        </section>
        </div>
    </x-slot:help>
</x-portal.auth-shell>
@endsection
