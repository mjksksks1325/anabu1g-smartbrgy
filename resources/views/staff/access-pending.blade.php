@extends('layouts.admin-iot')
@section('title', 'Staff access')
@section('content')
<section class="panel staff-submission">
    <h1>Access has not been assigned</h1>
    <p>Contact Super Admin to assign your staff pages and actions. Your account profile and security settings remain available.</p>
    @if(auth()->user()->hasAnyPermission(App\StaffPermissions::keys()))
        <a wire:navigate class="button" href="{{ route(App\StaffPermissions::landing(auth()->user())) }}">Open assigned workspace</a>
    @endif
</section>
@endsection
