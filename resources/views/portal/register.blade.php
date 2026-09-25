@extends('layouts.portal')
@section('title', 'Create account')
@section('band')
<x-portal.page-band title="Create account" title-id="register-title">
    <p>Ikonekta ang online account sa record ninyo sa Resident Records ng barangay.</p>
</x-portal.page-band>
@endsection
@section('content')
@php
    $stepNumber = ['name' => 1, 'details' => 2, 'email' => 3, 'code' => 3, 'account' => 4][$stage] ?? 1;
    $stepLabels = ['Hanapin ang record', 'Kumpirmahin ang details', 'Activation code', 'Gumawa ng password'];
@endphp
<div class="auth-layout">
    <section class="card card-accent" aria-labelledby="register-title">
        <div class="card-body">
            <ol class="progress-steps" aria-label="Mga hakbang sa pag-create ng account">
                @foreach($stepLabels as $index => $label)
                    <li @class(['done' => $index + 1 < $stepNumber]) @if($index + 1 === $stepNumber) aria-current="step" @endif><span>{{ $label }}</span></li>
                @endforeach
            </ol>
            <p class="step-count">Step {{ $stepNumber }} of 4: {{ $stepLabels[$stepNumber - 1] }}</p>

            @if($stage === 'account')
                <p class="alert alert-green" role="status">Na-verify na ang resident record. Tapusin ang pag-create ng account sa loob ng 10 minuto.</p>
                <form method="POST" action="{{ route('portal.register.store') }}" data-resident-form autocomplete="off">
                    @csrf
                    <div class="form-group">
                        <label class="form-label" for="registration-email">Email address</label>
                        <input class="form-input" id="registration-email" name="email" type="email" value="{{ $registrationEmail ?? old('email') }}" @if($registrationEmail) readonly aria-describedby="registration-email-help" @endif required maxlength="255" autocomplete="off" inputmode="email" autocapitalize="none" spellcheck="false" @error('email') aria-invalid="true" @enderror>
                        @error('email')<p class="field-error">{{ $message }}</p>@enderror
                        @if($registrationEmail)<p class="form-note" id="registration-email-help">Ito ang email na pinadalhan ng activation code. Ito rin ang gagamitin ninyo sa pag-log in.</p>@endif
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="registration-password">Password</label>
                        <input class="form-input" id="registration-password" name="password" type="password" required minlength="12" maxlength="255" autocomplete="new-password" aria-describedby="password-help" @error('password') aria-invalid="true" @enderror>
                        @error('password')<p class="field-error">{{ $message }}</p>@enderror
                        <p id="password-help" class="form-note">Hindi bababa sa 12 characters, may uppercase at lowercase letter, at may number.</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="registration-confirmation">Ulitin ang password</label>
                        <input class="form-input" id="registration-confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                    </div>
                    <button class="btn btn-green btn-full" type="submit">Create account</button>
                </form>
                <p class="next-note"><strong>Susunod:</strong> dadalhin kayo sa Log in page. Mag-log in gamit ang email at password na ito.</p>
            @elseif($stage === 'code')
                <p class="alert alert-green" role="status">I-check ang email ninyo para sa Resident Number at Activation Code. Valid ang code nang 24 oras.</p>
                @include('portal.partials.activation-form')
                <p class="next-note"><strong>Susunod:</strong> gagawa kayo ng password para sa account.</p>
                <details class="more">
                    <summary>Walang dumating o mali ang email?</summary>
                    <p class="form-note">Tingnan muna ang Spam o Promotions folder. Kung iba ang email na gusto ninyong gamitin, ulitin ang pag-verify ng record.</p>
                    <form method="POST" action="{{ route('portal.register.reset') }}">
                        @csrf
                        <button class="btn btn-outline btn-full" type="submit">Use a different email</button>
                    </form>
                </details>
            @elseif($stage === 'email' && ! $emailDeliveryAvailable)
                <p class="alert alert-blue" role="status">Na-verify na ang resident record. Hindi makapagpadala ng activation code sa email ngayon. Pumunta sa Barangay Hall at magpa-verify ng identity sa staff para mabigyan ng activation code, saka ito ilagay dito.</p>
                @include('portal.partials.activation-form')
                <p class="next-note"><strong>Susunod:</strong> gagawa kayo ng password para sa account.</p>
            @elseif($stage === 'email')
                <p class="alert alert-green" role="status">Na-verify na ang resident record. Ilagay ang email kung saan ipapadala ang activation code.</p>
                <form method="POST" action="{{ route('portal.register.send-code') }}" data-resident-form autocomplete="off">
                    @csrf
                    <div class="form-group">
                        <label class="form-label" for="activation-email">Email address</label>
                        <input class="form-input" id="activation-email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="off" inputmode="email" autocapitalize="none" spellcheck="false" aria-describedby="activation-email-help" @error('email') aria-invalid="true" @enderror>
                        @error('email')<p class="field-error">{{ $message }}</p>@enderror
                        <p class="form-note" id="activation-email-help">Gumamit ng email na nabubuksan ninyo. Ito rin ang gagamitin sa pag-log in.</p>
                    </div>
                    <button class="btn btn-green btn-full" type="submit">Send activation code</button>
                </form>
                <p class="next-note"><strong>Susunod:</strong> ipapadala sa email ang Resident Number at Activation Code ninyo. Valid ang code nang 24 oras.</p>
            @elseif($stage === 'details')
                <p class="alert alert-blue">May nakitang record na posibleng sa inyo. Kumpirmahin ang details na nasa barangay record para magpatuloy.</p>
                <form method="POST" action="{{ route('portal.register.confirm-record') }}" data-resident-form autocomplete="off">
                    @csrf
                    <div class="form-group">
                        <label class="form-label" for="registration-birth-date">Date of birth</label>
                        <input class="form-input" id="registration-birth-date" name="date_of_birth" type="date" required max="{{ now()->toDateString() }}" @error('date_of_birth') aria-invalid="true" @enderror>
                        @error('date_of_birth')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="registration-contact-last-four">Huling 4 na digit ng contact number na nasa record</label>
                        <input class="form-input" id="registration-contact-last-four" name="contact_last_four" type="text" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" required autocomplete="off" aria-describedby="contact-last-four-help" @error('contact_last_four') aria-invalid="true" @enderror>
                        @error('contact_last_four')<p class="field-error">{{ $message }}</p>@enderror
                        <p class="form-note" id="contact-last-four-help">Halimbawa: kung 0917 123 4567 ang number, ilagay ang 4567. Kung luma o wala ang contact number sa record, puwede itong i-update ng barangay staff.</p>
                    </div>
                    <button class="btn btn-green btn-full" type="submit">Confirm my record</button>
                </form>
                <p class="next-note"><strong>Susunod:</strong> maglalagay kayo ng email address kung saan ipapadala ang activation code.</p>
            @else
                <form method="POST" action="{{ route('portal.register.name') }}" data-resident-form autocomplete="off">
                    @csrf
                    <div class="form-group">
                        <label class="form-label" for="registration-full-name">Full name</label>
                        <input class="form-input" id="registration-full-name" name="full_name" type="text" value="{{ old('full_name') }}" required minlength="3" maxlength="255" autocomplete="off" aria-describedby="full-name-help" @error('full_name') aria-invalid="true" @enderror>
                        @error('full_name')<p class="field-error">{{ $message }}</p>@enderror
                        <p class="form-note" id="full-name-help">Isulat ang pangalan tulad ng nasa barangay record, kasama ang middle name. Hindi ito gagawa ng bagong resident record.</p>
                    </div>
                    <button class="btn btn-green btn-full" type="submit">Find my record</button>
                </form>
                <p class="next-note"><strong>Susunod:</strong> kukumpirmahin ninyo ang petsa ng kapanganakan at ang huling 4 na digit ng contact number na nasa record.</p>
            @endif

            @if($stage !== 'account' && $stage !== 'code' && ($stage !== 'email' || $emailDeliveryAvailable))
                <details class="more">
                    <summary>May activation code na mula sa barangay staff?</summary>
                    <p class="form-note">Ilagay ang Resident Number at Activation Code na ibinigay ng staff matapos i-verify ang identity ninyo.</p>
                    @include('portal.partials.activation-form')
                </details>
            @endif
            <p class="auth-links">May account na? <a href="{{ route('portal.login') }}">Log in</a> &middot; <a href="{{ route('password.request') }}">Nakalimutan ang password?</a></p>
        </div>
    </section>
    <aside class="auth-aside" aria-label="Paalala sa pag-create ng account">
        <section>
            <h2>Ihanda ang mga ito</h2>
            <ul class="plain-list">
                <li>Buong pangalan tulad ng nasa barangay record</li>
                <li>Petsa ng kapanganakan</li>
                <li>Huling 4 na digit ng contact number na nasa record</li>
                <li>Email address na nabubuksan ninyo</li>
            </ul>
        </section>
        <section>
            <h2>Hindi mahanap ang record?</h2>
            <p>Kung bagong lipat kayo o iba ang details sa record, pumunta sa Barangay Hall para magpa-register o magpa-update. Dalhin ang valid ID.</p>
        </section>
    </aside>
</div>
@endsection
