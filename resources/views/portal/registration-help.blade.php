@extends('layouts.portal')
@section('title', 'Registration assistance')
@section('content')
<section class="card"><div class="card-header"><h2>Registration could not be completed</h2></div><div class="card-body">
@if(session('existing_account'))
<p class="alert alert-blue">An online account is already associated with this resident record.</p>
@else
<p>We could not verify your information in the current Barangay Anabu I-G Resident Records.</p>
<ul class="resident-help-list"><li>If you recently moved here, your record may not yet be in the official database.</li><li>Your name or contact details in the barangay records may differ from what you entered.</li><li>Your record may still need verification or an update by barangay personnel.</li></ul>
<h3>What to do</h3><p>Please visit Barangay Anabu I-G Hall and ask staff to register, verify, or update your Resident Record. Bring a valid ID and any proof of residence required by the barangay. Staff can also help you obtain an activation code after verifying your identity.</p>
@endif
<div class="btn-row"><a class="btn btn-green" href="{{ route('portal.register') }}">Try again</a><a class="btn btn-outline" href="{{ route('portal.information') }}#help">Barangay information / Contact</a></div>
<p class="form-note">Already have an account? <a href="{{ route('portal.login') }}">Log in</a> or <a href="{{ route('password.request') }}">Forgot password</a>.</p>
</div></section>
@endsection
