@extends('layouts.admin-iot')
@section('title', 'Resident Records')
@section('content')
<section class="panel employee-access-panel registration-review">
    <div class="panel-heading registration-review-heading">
        <div><h1>New resident applications</h1><p>Review the submitted details before approving resident registration.</p></div>
        <a wire:navigate class="button quiet" href="{{ route('staff.residents.index') }}">Back to Resident Records</a>
    </div>
    <div class="registration-review-count"><strong>{{ $applications->total() }}</strong> applications <span>Portal account registration follows barangay approval.</span></div>
    @forelse($applications as $application)
        <details id="resident-application-{{ $application->id }}" class="employee-card registration-review-card" @if(($loop->first && $application->status === 'pending') || request()->integer('application') === $application->id) open @endif>
            <summary class="employee-intro">
                <div><h3>{{ App\Models\ResidentRegistrationApplication::fullName($application->details) }}</h3><p>{{ $application->reference }} &middot; {{ $application->created_at->format('M d, Y h:i A') }}</p></div>
                <span class="registration-review-status" data-application-state="{{ $application->status }}">{{ ucfirst(str_replace('_', ' ', $application->status)) }}</span>
                <span class="employee-expand-hint" aria-hidden="true"></span>
            </summary>
            <div class="employee-card-body">
                <div class="registration-review-details">
                    <figure class="registration-review-photo"><img src="{{ route('staff.resident-applications.photo', $application) }}" alt="Applicant resident photo" width="160" height="200" loading="lazy"><figcaption>Resident photo</figcaption></figure>
                    <dl class="detail-grid">
                        @foreach($application->details as $field => $value)<div @class(['registration-review-wide' => $field === 'address'])><dt>{{ ucfirst(str_replace('_', ' ', $field)) }}</dt><dd>{{ $value ?: 'Not provided' }}</dd></div>@endforeach
                        <div class="registration-review-wide"><dt>Email address</dt><dd>{{ $application->email }}</dd></div>
                    </dl>
                </div>
                @if($application->remarks || $application->reviewed_at)
                    <div class="registration-review-note">
                        @if($application->remarks)<h4>Review remarks</h4><p>{{ $application->remarks }}</p>@endif
                        @if($application->reviewed_at)<small>Reviewed {{ $application->reviewed_at->format('M d, Y h:i A') }} &middot; Staff #{{ $application->reviewed_by }}</small>@endif
                    </div>
                @endif
                @if($application->status === 'pending')
                    <form class="staff-submission-fields registration-review-form" method="POST" action="{{ route('staff.resident-applications.update', $application) }}">
                        @csrf
                        <h3 class="staff-submission-wide">Review decision</h3>
                        <label class="staff-submission-wide">Decision<select name="decision" required><option value="">Select a decision</option><option value="approved">Approve resident registration</option><option value="needs_correction">Request correction</option><option value="declined">Decline with reason</option></select></label>
                        <label class="staff-submission-wide">Remarks<textarea name="remarks" maxlength="1000" aria-describedby="review-remarks-help-{{ $application->id }}"></textarea><small id="review-remarks-help-{{ $application->id }}">Required when requesting correction or declining an application.</small></label>
                        <div class="staff-submission-wide registration-review-actions"><button class="button" type="submit">Save review</button></div>
                    </form>
                @elseif($application->status === 'approved')
                    <p class="registration-review-note">Resident record created. Approval does not send an activation code. The resident must continue portal account registration.</p>
                @endif
            </div>
        </details>
    @empty
        <div class="registration-review-empty"><x-staff-icon name="people" /><h2>No registration applications found.</h2><p>New submissions will appear here for authorized review.</p></div>
    @endforelse
    <div class="registration-review-pagination">{{ $applications->links() }}</div>
</section>
@endsection
