@extends('layouts.admin-iot')
@section('title', 'Incident Reports')
@section('content')
<section class="panel staff-submission">
    @isset($reference)
        <h1>Report submitted</h1>
        <div class="notice success" role="status">Report received. Reference: <strong>{{ $reference }}</strong></div>
        <p>Your report has been submitted for authorized staff review.</p>
        <a class="button" href="{{ route(auth()->user()->hasAnyPermission(['incidents.view', 'vawc.view']) ? 'staff.incidents.create' : 'staff.incidents.index') }}">Submit another report</a>
    @else
    <h1>Submit incident report</h1>
    <p>Record observed or reported facts. Reporting an allegation does not establish guilt.</p>
    @cannot('vawc.submit')
        <div class="notice" role="note">For VAWC/BPO concerns, refer to the designated authorized barangay personnel. Avoid entering sensitive details in this ordinary incident form. The system cannot reliably identify every sensitive report.</div>
    @endcannot
    <form class="staff-submission-fields" data-incident-submission-form method="POST" action="{{ route('staff.incidents.store') }}" enctype="multipart/form-data">
        @csrf
        <label>Incident category (required)
            <select name="incident_type_selection" data-incident-type-select aria-controls="custom-incident-type" required>
                <option value="" disabled @selected(! old('incident_type_selection', old('incident_type')))>Select type...</option>
                @foreach(\App\Models\Incident::SUBMISSION_CATEGORIES as $incidentType => $label)
                    <option value="{{ $incidentType }}" @selected(old('incident_type_selection', old('incident_type')) === $incidentType)>{{ $label }}</option>
                @endforeach
            </select>
            <span id="custom-incident-type" data-custom-incident-type @if(old('incident_type_selection') !== 'Iba pa') hidden @endif>
                <span>Specify incident type</span>
                <input name="incident_type" aria-label="Specify ordinary incident type" data-custom-incident-input value="{{ old('incident_type_selection') === 'Iba pa' ? old('incident_type') : '' }}" placeholder="Enter the incident type" maxlength="100" @if(old('incident_type_selection') === 'Iba pa') required @else disabled @endif>
            </span>
        </label>
        @can('vawc.submit')<label class="staff-submission-wide"><input type="checkbox" name="is_sensitive" value="1"> Restricted VAWC / BPO case</label>@endcan
        <label>Incident date (required)<input type="date" name="occurred_date" value="{{ old('occurred_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required></label>
        <label>Incident time (required)<input type="time" name="occurred_time" value="{{ old('occurred_time') }}" required></label>
        <label>Location / address (required)<input name="location" value="{{ old('location') }}" maxlength="255" required></label>
        <label>Purok (optional)<input name="purok" value="{{ old('purok') }}" maxlength="100"></label>
        <label>Reporting / involved person (optional if unknown)<input name="complainant_name" value="{{ old('complainant_name') }}" maxlength="255"></label>
        <label>Other involved person (optional if unknown)<input name="respondent_name" value="{{ old('respondent_name') }}" maxlength="255"></label>
        <label>Severity<select name="severity"><option value="low" @selected(old('severity', 'low') === 'low')>Low</option><option value="medium" @selected(old('severity') === 'medium')>Medium</option><option value="high" @selected(old('severity') === 'high')>High</option></select></label>
        <label class="staff-submission-wide">Factual description (required)<textarea name="details" aria-describedby="incident-description-help" minlength="10" maxlength="5000" required>{{ old('details') }}</textarea></label>
        <p class="staff-submission-wide" id="incident-description-help">Describe what you observed or what was reported to you. Distinguish observations from allegations. Do not state that a person is guilty.</p>
        <label class="staff-submission-wide">Immediate action taken (optional)<textarea name="immediate_action" maxlength="1000" placeholder="Record any immediate action you took, if applicable.">{{ old('immediate_action') }}</textarea></label>
        <label class="staff-submission-wide">Attachments (optional)<input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" aria-describedby="incident-attachment-help{{ $errors->has('attachments') || $errors->has('attachments.*') ? ' incident-attachment-error' : '' }}" @if($errors->has('attachments') || $errors->has('attachments.*')) aria-invalid="true" @endif></label>
        <p class="staff-submission-wide" id="incident-attachment-help">Up to five JPG, PNG, WebP or PDF files, 5 MB each.</p>
        @if($errors->has('attachments') || $errors->has('attachments.*'))
            <div class="notice error staff-submission-wide" id="incident-attachment-error" role="alert">
                @error('attachments')<p>{{ $message }}</p>@enderror
                @foreach($errors->get('attachments.*') as $messages)
                    @foreach($messages as $message)<p>{{ $message }}</p>@endforeach
                @endforeach
                <p>Select the attachments again before resubmitting.</p>
            </div>
        @endif
        <button class="button" type="submit">Submit report</button>
    </form>
    @endisset
</section>
<script src="{{ asset('js/staff-incident-submission.js') }}?v={{ filemtime(public_path('js/staff-incident-submission.js')) }}"></script>
@endsection
