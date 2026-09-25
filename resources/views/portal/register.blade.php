@extends('layouts.portal')
@section('title', 'Create Resident Account')
@section('content')
<div class="resident-auth-layout">
    <aside class="resident-auth-aside">
        <div class="portal-eyebrow">Join the Resident Portal</div>
        <h2>Your services start with a verified record.</h2>
        <p>Link your official resident record once, then use one account for document requests and updates.</p>
        <div class="resident-auth-aside-footer">
            <span>01 &nbsp; Find your official record</span>
            <span>02 &nbsp; Confirm your details and receive a code</span>
            <span>03 &nbsp; Create your account</span>
        </div>
    </aside>
    <section class="card resident-auth-card">
        <div class="card-header">
            <h2>Create Resident Account</h2>
            <p>{{ $verified ? 'Step 2: Set up your online account' : 'Verify your official resident record to continue' }}</p>
        </div>
        <div class="card-body">
            @if($stage === 'account')
                <p class="alert alert-green" role="status">Resident record verified. Complete account creation within 10 minutes.</p>
                <form method="POST" action="{{ route('portal.register.store') }}" data-resident-form autocomplete="off">
                    @csrf
                    <div class="form-group">
                        <label class="form-label" for="registration-email">Email address</label>
                        <input class="form-input" id="registration-email" name="email" type="email" value="{{ $registrationEmail ?? old('email') }}" @if($registrationEmail) readonly @endif required maxlength="255" autocomplete="off">
                        @if($registrationEmail)<p class="form-note">Use the email address that received your activation code.</p>@endif
                    </div>
                    <div class="form-group"><label class="form-label" for="registration-password">Password</label><input class="form-input" id="registration-password" name="password" type="password" required minlength="12" maxlength="255" autocomplete="new-password" aria-describedby="password-help"><p id="password-help" class="form-note">At least 12 characters, including uppercase and lowercase letters and a number.</p></div>
                    <div class="form-group"><label class="form-label" for="registration-confirmation">Confirm password</label><input class="form-input" id="registration-confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div>
                    <button class="btn btn-green btn-full" type="submit">Create account</button>
                </form>
            @elseif($stage === 'code')
                <p class="alert alert-green" role="status">Check your email for your Resident Number and Activation Code. The code expires in 24 hours.</p>
                @include('portal.partials.activation-form')
                <div class="resident-registration-alternative">
                    <p class="form-note">Need to use another email? Verify your resident record again to start a new request.</p>
                    <form method="POST" action="{{ route('portal.register.reset') }}">
                        @csrf
                        <button class="btn btn-outline btn-full" type="submit">Start over / Change email</button>
                    </form>
                </div>
            @elseif($stage === 'email' && ! $emailDeliveryAvailable)
                <p class="alert alert-blue resident-registration-note" role="status">Resident record verified. Email activation codes are unavailable right now. Ask barangay staff to verify your identity and issue a private activation code, then enter it below.</p>
                @include('portal.partials.activation-form')
            @elseif($stage === 'email')
                <p class="alert alert-green" role="status">Resident record verified. Enter an email address where we can send your registration code.</p>
                <form method="POST" action="{{ route('portal.register.send-code') }}" data-resident-form autocomplete="off">
                    @csrf
                    <div class="form-group"><label class="form-label" for="activation-email">Email address</label><input class="form-input" id="activation-email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="off"></div>
                    <button class="btn btn-green btn-full" type="submit">Send Activation Code</button>
                </form>
            @elseif($stage === 'details')
                <p class="alert alert-blue resident-registration-note">A possible record was found. Confirm the details already in the barangay record to continue registration.</p>
                <form method="POST" action="{{ route('portal.register.confirm-record') }}" data-resident-form autocomplete="off">
                    @csrf
                    <div class="form-group"><label class="form-label" for="registration-birth-date">Date of birth</label><input class="form-input" id="registration-birth-date" name="date_of_birth" type="date" required max="{{ now()->toDateString() }}"></div>
                    <div class="form-group"><label class="form-label" for="registration-contact-last-four">Last 4 digits of your contact number on record</label><input class="form-input" id="registration-contact-last-four" name="contact_last_four" type="text" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" required autocomplete="off"><p class="form-note">If your contact details are missing or outdated, barangay staff can help you update your record.</p></div>
                    <button class="btn btn-green btn-full" type="submit">Confirm Resident Record</button>
                </form>
            @else
                <p class="alert alert-blue resident-registration-note">Enter your name as it appears in the official Barangay Anabu I-G Resident Records. This step does not create a new resident record.</p>
                <form method="POST" action="{{ route('portal.register.name') }}" data-resident-form autocomplete="off">
                    @csrf
                    <div class="form-group"><label class="form-label" for="registration-full-name">Full name</label><input class="form-input" id="registration-full-name" name="full_name" type="text" value="{{ old('full_name') }}" required minlength="3" maxlength="255" autocomplete="off"></div>
                    <button class="btn btn-green btn-full" type="submit">Verify Resident Record</button>
                </form>
            @endif

            @if($stage !== 'account' && $stage !== 'code' && ($stage !== 'email' || $emailDeliveryAvailable))
                <details class="resident-registration-alternative">
                    <summary>Already have an activation code from barangay staff?</summary>
                    <p class="form-note">You can use the code provided after staff verified your identity.</p>
                    @include('portal.partials.activation-form')
                </details>
            @endif
            <p class="form-note">Already registered? <a href="{{ route('portal.login') }}">Log in</a> or <a href="{{ route('password.request') }}">recover your account</a>.</p>
        </div>
    </section>
</div>
@endsection
