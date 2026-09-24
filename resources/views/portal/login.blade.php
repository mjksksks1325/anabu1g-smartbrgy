@extends('layouts.portal')
@section('title', 'Resident login')
@section('content')
<div class="resident-auth-layout"><aside class="resident-auth-aside"><div class="portal-eyebrow">Your barangay, online</div><h2>Welcome back to your community portal.</h2><p>Use your verified resident account to request documents and follow their progress.</p><div class="resident-auth-aside-footer"><span>01 &nbsp; Secure access</span><span>02 &nbsp; Official barangay review</span><span>03 &nbsp; Request tracking</span></div></aside>
<section class="card resident-auth-card"><div class="card-header"><div class="portal-eyebrow">Resident Portal</div><h2>Sign in</h2><p>Enter the account linked to your official resident record.</p></div>
<form class="card-body" method="POST" action="{{ route('portal.login.store') }}" data-resident-form>@csrf
<div class="form-group"><label class="form-label" for="resident-email">Email address</label><input class="form-input" id="resident-email" name="email" type="email" autocomplete="username" required maxlength="255"></div>
<div class="form-group"><label class="form-label" for="resident-password">Password</label><input class="form-input" id="resident-password" name="password" type="password" autocomplete="current-password" required></div>
<label class="resident-remember"><input name="remember" type="checkbox" value="1"> Keep me signed in on this device</label>
<button class="btn btn-green btn-full" type="submit">Log in</button>
<p class="form-note"><a href="{{ route('password.request') }}">Forgot password?</a> &middot; <a href="{{ route('portal.register') }}">Create Resident Account</a></p>
</form></section></div>
@endsection
