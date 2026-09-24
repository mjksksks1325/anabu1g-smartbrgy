@extends('layouts.admin-iot')
@section('title', 'Employee Cabinet Access')
@section('content')
<div class="eyebrow">Administration / Cabinet Authorization</div>
<div class="page-heading"><div><h1>Employee Cabinet Access</h1><p>Manage cabinet permission separately from website roles. Both RFID and face enrollment are required for effective access.</p></div></div>
<div class="notice">Enrollment requests are recorded as pending until Raspberry Pi integration is available. They do not enroll a credential or open a cabinet.</div>
@unless($hasCompletedEnrollment)<div class="notice">No employees have completed cabinet enrollment yet.</div>@endunless
<form class="filter-panel compact" method="GET" action="{{ route('admin.cabinet-access.index') }}"><label>Find employee<input type="search" name="search" value="{{ $search }}" placeholder="Name or email"></label><button class="button" type="submit">Search</button></form>
<section class="panel"><div class="panel-heading"><h2>Employee permissions</h2><span>{{ $employees->total() }} employees</span></div>
@forelse($employees as $employee)
    @php($access = $employee->cabinetAccess)
    <article class="employee-card"><div class="employee-intro"><div><h3>{{ $employee->name }}</h3><p>Employee #{{ $employee->id }} · {{ $employee->email }} · {{ ucfirst($employee->role) }} · Website {{ $employee->is_active ? 'active' : 'inactive' }}</p></div><span class="pill {{ $access?->isEffective() ? 'success' : 'neutral' }}">{{ $access?->isEffective() ? 'Effective access' : 'No effective access' }}</span></div>
    <div class="detail-grid"><div><dt>Cabinet permission</dt><dd>{{ $access?->is_active ? 'Enabled' : 'Disabled' }}</dd></div><div><dt>RFID enrollment</dt><dd>{{ str_replace('_', ' ', ucfirst($access?->rfid_enrollment_status ?? 'not_started')) }} · {{ $access?->rfid_enrolled_at?->format('M j, Y g:i A') ?? 'No completion date' }}</dd></div><div><dt>Face enrollment</dt><dd>{{ str_replace('_', ' ', ucfirst($access?->face_enrollment_status ?? 'not_started')) }} · {{ $access?->face_enrolled_at?->format('M j, Y g:i A') ?? 'No completion date' }}</dd></div><div><dt>Authorization version</dt><dd>{{ $access?->authorization_version ?? '—' }} · Updated {{ $access?->updated_at?->format('M j, Y g:i A') ?? 'Never' }}</dd></div></div>
    <div class="employee-mapping-status"><strong>RPi Employee ID</strong><span>{{ $access?->rpi_employee_id ?? 'Not linked' }}</span></div>
    <form class="employee-mapping-form" method="POST" action="{{ route('admin.cabinet-access.rpi-employee-id.update', $employee) }}">
        @csrf @method('PATCH')
        <div><label for="rpi-employee-id-{{ $employee->id }}">Link RPi Employee ID</label><input id="rpi-employee-id-{{ $employee->id }}" name="rpi_employee_id" type="text" value="{{ $access?->rpi_employee_id }}" maxlength="32" placeholder="EMP001" autocomplete="off" aria-describedby="rpi-employee-hint-{{ $employee->id }}"><small id="rpi-employee-hint-{{ $employee->id }}">Use the stable FaceLock employee ID, not the RFID card UID. Clear and save to unlink.</small></div>
        <button class="button quiet" type="submit">Save RPi ID</button>
    </form>
    <div class="employee-actions">
    @if($access?->is_active)
        <button class="button quiet" type="button" onclick="document.getElementById('cabinet-revoke-{{ $employee->id }}').showModal()">Deactivate cabinet access</button>
        <dialog class="confirm-dialog" id="cabinet-revoke-{{ $employee->id }}" aria-labelledby="cabinet-revoke-title-{{ $employee->id }}"><h3 id="cabinet-revoke-title-{{ $employee->id }}">Deactivate cabinet access?</h3><p>{{ $employee->name }} will no longer have effective cabinet access. Their file movement and audit history will remain available.</p><div class="dialog-actions"><form method="dialog"><button class="button quiet" type="submit">Cancel</button></form><form method="POST" action="{{ route('admin.cabinet-access.update', $employee) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="0"><button class="button danger" type="submit">Deactivate access</button></form></div></dialog>
    @else
        <form method="POST" action="{{ route('admin.cabinet-access.update', $employee) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="1"><button class="button" type="submit" @disabled(! $employee->is_active)>Enable cabinet access</button></form>
    @endif
    @foreach(['rfid' => 'Request RFID enrollment', 'face' => 'Request face enrollment'] as $method => $label)
        <form method="POST" action="{{ route('admin.cabinet-access.enroll', [$employee, $method]) }}">@csrf<button class="button quiet" type="submit" @disabled(! $employee->is_active || ! $access?->is_active || $access->{$method.'_enrollment_status'} !== 'not_started')>{{ $label }}</button></form>
    @endforeach</div></article>
@empty
    <div class="empty-state"><strong>No employees found</strong><p>Try another name or email.</p></div>
@endforelse
<div class="pagination">{{ $employees->links() }}</div></section>
@endsection
