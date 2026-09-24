@extends('layouts.portal')
@section('title', 'Registration assistance')
@section('content')
<section class="card"><div class="card-header"><h2>Registration could not be completed</h2></div><div class="card-body">
@if(session('existing_account'))
<p class="alert alert-blue">An online account is already associated with this resident record.</p>
@else
<p>We could not verify your information against the official Resident Records of Barangay Anabu I-G.</p>
<ul class="resident-help-list"><li>You may not yet be listed in the barangay resident records.</li><li>Your information or activation code may not match or may need updating.</li><li>Your resident record may not currently be eligible for online registration.</li></ul>
<h3>What to do</h3><p>Please visit Barangay Anabu I-G for assistance with resident registration, record updating, or an activation code. After your record has been registered or updated, you may return to create your online account.</p>
@endif
<div class="btn-row"><a class="btn btn-green" href="{{ route('portal.register') }}">Try again</a><a class="btn btn-outline" href="{{ route('portal.information') }}#help">Barangay information / Contact</a></div>
<p class="form-note">Already have an account? <a href="{{ route('portal.login') }}">Log in</a> or <a href="{{ route('password.request') }}">Forgot password</a>.</p>
</div></section>
@endsection
