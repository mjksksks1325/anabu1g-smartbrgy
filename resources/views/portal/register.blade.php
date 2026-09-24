@extends('layouts.portal')
@section('title', 'Create Resident Account')
@section('content')
<div class="resident-auth-layout"><aside class="resident-auth-aside"><div class="portal-eyebrow">Join the Resident Portal</div><h2>Your services start with a verified record.</h2><p>Link your official resident record once, then use one account for document requests and updates.</p><div class="resident-auth-aside-footer"><span>01 &nbsp; Get an activation code from barangay staff</span><span>02 &nbsp; Verify your resident number</span><span>03 &nbsp; Create your account</span></div></aside>
<section class="card resident-auth-card"><div class="card-header"><h2>Create Resident Account</h2><p>{{ $verified ? 'Step 2: Set up your online account' : 'Step 1: Verify your resident record' }}</p></div><div class="card-body">
@if($verified)
<p class="alert alert-green">Your resident record has been verified. Complete account creation within 10 minutes.</p>
<form method="POST" action="{{ route('portal.register.store') }}" data-resident-form autocomplete="off">@csrf
<div class="form-group"><label class="form-label" for="registration-email">Email address</label><input class="form-input" id="registration-email" name="email" type="email" required maxlength="255" autocomplete="off"></div>
<div class="form-group"><label class="form-label" for="registration-password">Password</label><input class="form-input" id="registration-password" name="password" type="password" required minlength="12" maxlength="255" autocomplete="new-password" aria-describedby="password-help"><p id="password-help" class="form-note">At least 12 characters, including uppercase and lowercase letters and a number.</p></div>
<div class="form-group"><label class="form-label" for="registration-confirmation">Confirm password</label><input class="form-input" id="registration-confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div>
<button class="btn btn-green btn-full" type="submit">Create account</button>
</form>
@else
<div class="alert alert-blue resident-registration-note"><div>You must already be listed in the official Resident Records. Ask authorized barangay staff to open your record in <strong>Resident Records</strong>, verify your identity, then select <strong>Issue activation code</strong>. The private code expires after 24 hours and must not be shared.</div></div>
<form method="POST" action="{{ route('portal.register.verify') }}" data-resident-form autocomplete="off">@csrf
<div class="form-group"><label class="form-label" for="resident-number">Resident number</label><input class="form-input" id="resident-number" name="resident_number" required maxlength="40" autocomplete="off"></div>
<div class="form-group"><label class="form-label" for="activation-code">Activation code</label><input class="form-input" id="activation-code" name="activation_code" type="password" required maxlength="100" autocomplete="off" spellcheck="false"></div>
<button class="btn btn-green btn-full" type="submit">Verify resident record</button>
</form>
@endif
<p class="form-note">Already registered? <a href="{{ route('portal.login') }}">Log in</a> or <a href="{{ route('password.request') }}">recover your account</a>.</p>
</div></section></div>
@endsection
