@extends('layouts.portal')
@section('title', 'Resident profile')
@section('content')
<section class="card" data-resident-private><div class="card-header"><h2>Resident profile</h2><p>Official resident information is maintained by authorized barangay personnel.</p></div><div class="card-body">
<dl class="resident-profile-details"><dt>Resident number</dt><dd>{{ $resident->resident_number }}</dd><dt>Name</dt><dd>{{ $resident->full_name }}</dd><dt>Address</dt><dd>{{ $resident->address }}</dd><dt>Account email</dt><dd>{{ auth('resident')->user()->email }}</dd></dl>
<p class="form-note">For corrections to your resident record or account email, please request barangay assistance.</p><a class="btn btn-outline" href="{{ route('password.request') }}">Reset password</a>
</div></section>
@endsection
