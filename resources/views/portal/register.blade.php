@extends('layouts.portal')
@section('title', __('Create account'))
@section('band')
<x-portal.page-band title="Create your resident account" title-id="register-title">
    <p><span data-portal-i18n="Ikonekta ang inyong online account sa verified Resident Records ng barangay.">{{ __('Ikonekta ang inyong online account sa verified Resident Records ng barangay.') }}</span></p>
</x-portal.page-band>
@endsection
@section('content')
@php
    $stepNumber = ['name' => 1, 'details' => 2, 'email' => 3, 'code' => 3, 'account' => 4][$stage] ?? 1;
    $stepLabels = ['Find resident record', 'Verify identity', 'Email verification', 'Create account'];
    $stepTitles = ['Hanapin ang record', 'Kumpirmahin ang details', 'Activation code', 'Gumawa ng password'];
@endphp
<div class="auth-layout">
    <section class="card card-accent" aria-labelledby="register-title">
        <div class="card-body">
            <ol class="progress-steps" data-portal-i18n-aria-label="Mga hakbang sa pag-create ng account" aria-label="{{ __('Mga hakbang sa pag-create ng account') }}">
                @foreach($stepLabels as $index => $label)
                    <li @class(['done' => $index + 1 < $stepNumber]) @if($index + 1 === $stepNumber) aria-current="step" @endif><span><span data-portal-i18n="{{ $label }}">{{ __($label) }}</span></span></li>
                @endforeach
            </ol>
            <p class="step-count" data-portal-i18n="Step :current of :total" data-portal-params="{{ json_encode(['current' => $stepNumber, 'total' => 4]) }}">{{ __('Step :current of :total', ['current' => $stepNumber, 'total' => 4]) }}</p>
            <h2 class="registration-step-title"><span data-portal-i18n="{{ $stepTitles[$stepNumber - 1] }}">{{ __($stepTitles[$stepNumber - 1]) }}</span></h2>
            @php($guidance = [1 => 'Ilagay ang pangalang nasa opisyal na resident record.', 2 => 'Kumpirmahin ang pribadong detalye ng record ninyo.', 3 => $emailDeliveryAvailable ? 'Gamitin ang email na mabubuksan ninyo para sa activation code.' : 'Humingi ng activation code sa barangay staff matapos ma-verify ang identity ninyo.', 4 => 'Gumawa ng ligtas na password para sa account.'][$stepNumber])
            <p class="step-guidance" data-portal-i18n="{{ $guidance }}">{{ __($guidance) }}</p>

            @if($stage === 'account')
                <p class="alert alert-green" role="status"><span data-portal-i18n="Na-verify na ang resident record. Tapusin ang pag-create ng account sa loob ng 10 minuto.">{{ __('Na-verify na ang resident record. Tapusin ang pag-create ng account sa loob ng 10 minuto.') }}</span></p>
                <form method="POST" action="{{ route('portal.register.store') }}" enctype="multipart/form-data" data-resident-form autocomplete="off">
                    @csrf
                    <div class="form-group">
                        <label class="form-label" for="registration-email"><span data-portal-i18n="Email address">{{ __('Email address') }}</span></label>
                        <input class="form-input" id="registration-email" name="email" type="email" value="{{ $registrationEmail ?? old('email') }}" @if($registrationEmail) readonly aria-describedby="registration-email-help" @endif required maxlength="255" autocomplete="off" inputmode="email" autocapitalize="none" spellcheck="false" @error('email') aria-invalid="true" @enderror>
                        @error('email')<p class="field-error" data-portal-message>{{ $message }}</p>@enderror
                        @if($registrationEmail)<p class="form-note" id="registration-email-help"><span data-portal-i18n="Ito ang email na pinadalhan ng activation code. Ito rin ang gagamitin ninyo sa pag-log in.">{{ __('Ito ang email na pinadalhan ng activation code. Ito rin ang gagamitin ninyo sa pag-log in.') }}</span></p>@endif
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="registration-photo"><span data-portal-i18n="Resident photo *">{{ __('Resident photo *') }}</span></label>
                        <input class="form-input" id="registration-photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required aria-describedby="registration-photo-help" @error('photo') aria-invalid="true" @enderror>
                        @error('photo')<p class="field-error" data-portal-message>{{ $message }}</p>@enderror
                        <p class="form-note" id="registration-photo-help"><span data-portal-i18n="Mag-upload ng malinaw na larawan ng inyong mukha. JPG, PNG, o WebP; hanggang 5 MB. Piliin ulit ang larawan kung may kailangang itama sa form.">{{ __('Mag-upload ng malinaw na larawan ng inyong mukha. JPG, PNG, o WebP; hanggang 5 MB. Piliin ulit ang larawan kung may kailangang itama sa form.') }}</span></p>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="registration-password"><span data-portal-i18n="Password">{{ __('Password') }}</span></label>
                        <input class="form-input" id="registration-password" name="password" type="password" required minlength="12" maxlength="255" autocomplete="new-password" aria-describedby="password-help" @error('password') aria-invalid="true" @enderror>
                        @error('password')<p class="field-error" data-portal-message>{{ $message }}</p>@enderror
                        <p id="password-help" class="form-note"><span data-portal-i18n="Hindi bababa sa 12 characters, may uppercase at lowercase letter, at may number.">{{ __('Hindi bababa sa 12 characters, may uppercase at lowercase letter, at may number.') }}</span></p>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="registration-confirmation"><span data-portal-i18n="Ulitin ang password">{{ __('Ulitin ang password') }}</span></label>
                        <input class="form-input" id="registration-confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                    </div>
                    <button class="btn btn-green btn-full" type="submit"><span data-portal-i18n="Create account">{{ __('Create account') }}</span></button>
                </form>
                <p class="next-note"><strong><span data-portal-i18n="Susunod:">{{ __('Susunod:') }}</span></strong> <span data-portal-i18n="dadalhin kayo sa Log in page. Mag-log in gamit ang email at password na ito.">{{ __('dadalhin kayo sa Log in page. Mag-log in gamit ang email at password na ito.') }}</span></p>
            @elseif($stage === 'code')
                <p class="alert alert-green" role="status"><span data-portal-i18n="I-check ang email ninyo para sa Resident Number at Activation Code. Valid ang code nang 24 oras.">{{ __('I-check ang email ninyo para sa Resident Number at Activation Code. Valid ang code nang 24 oras.') }}</span></p>
                @include('portal.partials.activation-form')
                <p class="next-note"><strong><span data-portal-i18n="Susunod:">{{ __('Susunod:') }}</span></strong> <span data-portal-i18n="gagawa kayo ng password para sa account.">{{ __('gagawa kayo ng password para sa account.') }}</span></p>
                <details class="more">
                    <summary><span data-portal-i18n="Walang dumating o mali ang email?">{{ __('Walang dumating o mali ang email?') }}</span></summary>
                    <p class="form-note"><span data-portal-i18n="Tingnan muna ang Spam o Promotions folder. Kung iba ang email na gusto ninyong gamitin, ulitin ang pag-verify ng record.">{{ __('Tingnan muna ang Spam o Promotions folder. Kung iba ang email na gusto ninyong gamitin, ulitin ang pag-verify ng record.') }}</span></p>
                    <form method="POST" action="{{ route('portal.register.reset') }}">
                        @csrf
                        <button class="btn btn-outline btn-full" type="submit"><span data-portal-i18n="Use a different email">{{ __('Use a different email') }}</span></button>
                    </form>
                </details>
            @elseif($stage === 'email' && ! $emailDeliveryAvailable)
                <p class="alert alert-blue" role="status"><span data-portal-i18n="Na-verify na ang resident record. Hindi makapagpadala ng activation code sa email ngayon. Pumunta sa Barangay Hall at magpa-verify ng identity sa staff para mabigyan ng activation code, saka ito ilagay dito.">{{ __('Na-verify na ang resident record. Hindi makapagpadala ng activation code sa email ngayon. Pumunta sa Barangay Hall at magpa-verify ng identity sa staff para mabigyan ng activation code, saka ito ilagay dito.') }}</span></p>
                @include('portal.partials.activation-form')
                <p class="next-note"><strong><span data-portal-i18n="Susunod:">{{ __('Susunod:') }}</span></strong> <span data-portal-i18n="gagawa kayo ng password para sa account.">{{ __('gagawa kayo ng password para sa account.') }}</span></p>
            @elseif($stage === 'email')
                <p class="alert alert-green" role="status"><span data-portal-i18n="Na-verify na ang resident record. Ilagay ang email kung saan ipapadala ang activation code.">{{ __('Na-verify na ang resident record. Ilagay ang email kung saan ipapadala ang activation code.') }}</span></p>
                <form method="POST" action="{{ route('portal.register.send-code') }}" data-resident-form autocomplete="off">
                    @csrf
                    <div class="form-group">
                        <label class="form-label" for="activation-email"><span data-portal-i18n="Email address">{{ __('Email address') }}</span></label>
                        <input class="form-input" id="activation-email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="off" inputmode="email" autocapitalize="none" spellcheck="false" aria-describedby="activation-email-help" @error('email') aria-invalid="true" @enderror>
                        @error('email')<p class="field-error" data-portal-message>{{ $message }}</p>@enderror
                        <p class="form-note" id="activation-email-help"><span data-portal-i18n="Gumamit ng email na nabubuksan ninyo. Ito rin ang gagamitin sa pag-log in.">{{ __('Gumamit ng email na nabubuksan ninyo. Ito rin ang gagamitin sa pag-log in.') }}</span></p>
                    </div>
                    <button class="btn btn-green btn-full" type="submit"><span data-portal-i18n="Send activation code">{{ __('Send activation code') }}</span></button>
                </form>
                <p class="next-note"><strong><span data-portal-i18n="Susunod:">{{ __('Susunod:') }}</span></strong> <span data-portal-i18n="ipapadala sa email ang Resident Number at Activation Code ninyo. Valid ang code nang 24 oras.">{{ __('ipapadala sa email ang Resident Number at Activation Code ninyo. Valid ang code nang 24 oras.') }}</span></p>
            @elseif($stage === 'details')
                <p class="alert alert-blue"><span data-portal-i18n="May nakitang record na posibleng sa inyo. Kumpirmahin ang details na nasa barangay record para magpatuloy.">{{ __('May nakitang record na posibleng sa inyo. Kumpirmahin ang details na nasa barangay record para magpatuloy.') }}</span></p>
                <form method="POST" action="{{ route('portal.register.confirm-record') }}" data-resident-form autocomplete="off">
                    @csrf
                    <div class="form-group">
                        <label class="form-label" for="registration-birth-date"><span data-portal-i18n="Date of birth">{{ __('Date of birth') }}</span></label>
                        <input class="form-input" id="registration-birth-date" name="date_of_birth" type="date" value="{{ old('date_of_birth') }}" required max="{{ now()->toDateString() }}" @error('date_of_birth') aria-invalid="true" @enderror>
                        @error('date_of_birth')<p class="field-error" data-portal-message>{{ $message }}</p>@enderror
                    </div>
                    <button class="btn btn-green btn-full" type="submit"><span data-portal-i18n="Confirm my record">{{ __('Confirm my record') }}</span></button>
                </form>
                <p class="next-note"><strong><span data-portal-i18n="Susunod:">{{ __('Susunod:') }}</span></strong> <span data-portal-i18n="maglalagay kayo ng email address kung saan ipapadala ang activation code.">{{ __('maglalagay kayo ng email address kung saan ipapadala ang activation code.') }}</span></p>
            @else
                <form method="POST" action="{{ route('portal.register.name') }}" data-resident-form autocomplete="off">
                    @csrf
                    <div class="form-group">
                        <label class="form-label" for="registration-full-name"><span data-portal-i18n="Full name">{{ __('Full name') }}</span></label>
                        <input class="form-input" id="registration-full-name" name="full_name" type="text" value="{{ old('full_name') }}" required minlength="3" maxlength="255" autocomplete="off" aria-describedby="full-name-help" @error('full_name') aria-invalid="true" @enderror>
                        @error('full_name')<p class="field-error" data-portal-message>{{ $message }}</p>@enderror
                        <p class="form-note" id="full-name-help"><span data-portal-i18n="Isulat ang pangalan tulad ng nasa barangay record, kasama ang middle name. Hindi ito gagawa ng bagong resident record.">{{ __('Isulat ang pangalan tulad ng nasa barangay record, kasama ang middle name. Hindi ito gagawa ng bagong resident record.') }}</span></p>
                    </div>
                    <button class="btn btn-green btn-full" type="submit"><span data-portal-i18n="Find my record">{{ __('Find my record') }}</span></button>
                </form>
                <p class="next-note"><strong><span data-portal-i18n="Susunod:">{{ __('Susunod:') }}</span></strong> <span data-portal-i18n="kukumpirmahin ninyo ang petsa ng kapanganakan na nasa barangay record.">{{ __('kukumpirmahin ninyo ang petsa ng kapanganakan na nasa barangay record.') }}</span></p>
            @endif

            @if($stage !== 'account' && $stage !== 'code' && ($stage !== 'email' || $emailDeliveryAvailable))
                <details class="more">
                    <summary><span data-portal-i18n="May activation code na mula sa barangay staff?">{{ __('May activation code na mula sa barangay staff?') }}</span></summary>
                    <p class="form-note"><span data-portal-i18n="Ilagay ang Resident Number at Activation Code na ibinigay ng staff matapos i-verify ang identity ninyo.">{{ __('Ilagay ang Resident Number at Activation Code na ibinigay ng staff matapos i-verify ang identity ninyo.') }}</span></p>
                    @include('portal.partials.activation-form')
                </details>
            @endif
            <p class="auth-links"><span data-portal-i18n="May account na?">{{ __('May account na?') }}</span> <a href="{{ route('portal.login') }}"><span data-portal-i18n="Log in">{{ __('Log in') }}</span></a> &middot; <a href="{{ route('password.request') }}"><span data-portal-i18n="Nakalimutan ang password?">{{ __('Nakalimutan ang password?') }}</span></a></p>
        </div>
    </section>
    <aside class="auth-aside" data-portal-i18n-aria-label="Paalala sa pag-create ng account" aria-label="{{ __('Paalala sa pag-create ng account') }}">
        <section>
            <h2><span data-portal-i18n="Ihanda ang mga ito">{{ __('Ihanda ang mga ito') }}</span></h2>
            <ul class="plain-list">
                <li><span data-portal-i18n="Buong pangalan tulad ng nasa barangay record">{{ __('Buong pangalan tulad ng nasa barangay record') }}</span></li>
                <li><span data-portal-i18n="Petsa ng kapanganakan">{{ __('Petsa ng kapanganakan') }}</span></li>
                <li><span data-portal-i18n="Email address na nabubuksan ninyo">{{ __('Email address na nabubuksan ninyo') }}</span></li>
                <li><span data-portal-i18n="Malinaw na resident photo (JPG, PNG, o WebP; hanggang 5 MB)">{{ __('Malinaw na resident photo (JPG, PNG, o WebP; hanggang 5 MB)') }}</span></li>
            </ul>
        </section>
        <section>
            <h2><span data-portal-i18n="Hindi mahanap ang record?">{{ __('Hindi mahanap ang record?') }}</span></h2>
            <p><span data-portal-i18n="Kung bagong lipat kayo o iba ang details sa record, pumunta sa Barangay Hall para magpa-register o magpa-update. Dalhin ang valid ID.">{{ __('Kung bagong lipat kayo o iba ang details sa record, pumunta sa Barangay Hall para magpa-register o magpa-update. Dalhin ang valid ID.') }}</span></p>
        </section>
        <section>
            <h2><span data-portal-i18n="Data Privacy Notice">{{ __('Data Privacy Notice') }}</span></h2>
            <p><span data-portal-i18n="Gagamitin lamang ang inyong impormasyon para i-verify at gawin ang resident portal account.">{{ __('Gagamitin lamang ang inyong impormasyon para i-verify at gawin ang resident portal account.') }}</span></p>
        </section>
    </aside>
</div>
@endsection
