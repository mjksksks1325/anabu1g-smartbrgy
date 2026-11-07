@extends('layouts.portal')
@section('title', __('New resident registration'))
@section('content')
<x-portal.auth-shell title="New resident registration" title-id="new-resident-title" back="Create account" :back-url="route('portal.register')">
    <section class="card rp-form-card new-resident-card" aria-labelledby="new-resident-title">
        <div class="card-body">
            @if($application)
                @php($statusLabel = ['pending' => 'Pending review', 'approved' => 'Approved', 'needs_correction' => 'Needs correction', 'declined' => 'Declined'][$application->status])
                <div class="new-resident-status" data-application-state="{{ $application->status }}" role="status">
                    <span class="new-resident-status-badge" data-portal-i18n="{{ $statusLabel }}">{{ __($statusLabel) }}</span>
                    <h2><span data-portal-i18n="Application reference">{{ __('Application reference') }}</span></h2>
                    <strong class="new-resident-reference">{{ $application->reference }}</strong>
                    @if($application->remarks)<div class="new-resident-review-note"><span data-portal-i18n="Review remarks">{{ __('Review remarks') }}</span><p>{{ $application->remarks }}</p></div>@endif
                    @if($application->status === 'approved')
                        <p><span data-portal-i18n="Your resident registration is approved. To open your portal account, visit the Barangay Hall with a valid ID. Staff will verify your identity and give you an activation code to enter on the registration page.">{{ __('Your resident registration is approved. To open your portal account, visit the Barangay Hall with a valid ID. Staff will verify your identity and give you an activation code to enter on the registration page.') }}</span></p>
                    @elseif($application->status === 'pending')
                        <p><span data-portal-i18n="No portal account has been created yet. Save your reference and return here using the same browser session to check the review.">{{ __('No portal account has been created yet. Save your reference and return here using the same browser session to check the review.') }}</span></p>
                    @endif
                </div>
            @else
                <div class="new-resident-intro">
                    <h2><span data-portal-i18n="Resident details">{{ __('Resident details') }}</span></h2>
                    <p><span data-portal-i18n="For new residents without a barangay record only. Existing residents can continue through activation.">{{ __('For new residents without a barangay record only. Existing residents can continue through activation.') }}</span></p>
                    <small data-portal-i18n="Fields marked * are required.">{{ __('Fields marked * are required.') }}</small>
                </div>
            @endif
            @if(!$application || $application->status === 'needs_correction')
                <form class="new-resident-form" method="POST" action="{{ route('portal.new-resident.store') }}" enctype="multipart/form-data" data-resident-form>
                    @csrf
                    @include('portal.partials.new-resident-privacy-notice')
                    @php($sections = [
                        'Personal information' => ['first_name' => 'First name', 'last_name' => 'Last name', 'middle_name' => 'Middle name', 'suffix' => 'Suffix', 'date_of_birth' => 'Date of birth', 'gender' => 'Gender', 'civil_status' => 'Civil status'],
                        'Residential address' => ['purok' => 'Purok', 'residency_type' => 'Residency type', 'address' => 'Address'],
                        'Contact details' => ['email' => 'Email address', 'contact_number' => 'Contact number'],
                    ])
                    @foreach($sections as $title => $fields)
                        <fieldset class="new-resident-section">
                            <legend><span class="new-resident-section-number" aria-hidden="true">{{ $loop->iteration }}</span><span data-portal-i18n="{{ $title }}">{{ __($title) }}</span></legend>
                            <div class="new-resident-fields">
                                @foreach($fields as $field => $label) @include('portal.partials.new-resident-field', ['field' => $field, 'label' => $label]) @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                    <fieldset class="new-resident-section">
                        <legend><span class="new-resident-section-number" aria-hidden="true">4</span><span data-portal-i18n="Resident photo">{{ __('Resident photo') }}</span></legend>
                        <div class="new-resident-upload">
                            <label class="form-label" for="application-photo"><span data-portal-i18n="Resident photo">{{ __('Resident photo') }}</span> *</label>
                            <input class="form-input" type="file" id="application-photo" data-photo-preview="application-photo-preview" name="photo" accept=".jpg,.jpeg,.png,.gif,.bmp,.webp,.avif,.heic,.heif,image/jpeg,image/png,image/gif,image/bmp,image/webp,image/avif,image/heic,image/heif" data-resident-image-only data-error-target="application-photo-client-error" required aria-describedby="application-photo-help application-photo-client-error @error('photo') application-photo-error @enderror" @error('photo') aria-invalid="true" @enderror>
                            <figure class="rp-photo-preview" id="application-photo-preview" hidden><img alt="" width="120" height="120"><figcaption><span data-portal-i18n="Ito ang napiling larawan.">{{ __('Ito ang napiling larawan.') }}</span></figcaption></figure>
                        <p class="form-note" id="application-photo-help"><span data-portal-i18n="Supported image files only, up to 10 MB. Documents are not accepted. Select the photo again after a validation error or correction.">{{ __('Supported image files only, up to 10 MB. Documents are not accepted. Select the photo again after a validation error or correction.') }}</span></p>
                            <p class="field-error" id="application-photo-client-error" hidden role="alert" aria-live="polite"><span data-portal-i18n="Choose an image file only (up to 10 MB). Documents are not accepted.">{{ __('Choose an image file only (up to 10 MB). Documents are not accepted.') }}</span></p>
                            @error('photo')<p class="field-error" id="application-photo-error" data-portal-message>{{ $message }}</p>@enderror
                        </div>
                    </fieldset>
                    @error('application')<p class="field-error" data-portal-message>{{ $message }}</p>@enderror
                    <div class="new-resident-form-actions"><button class="btn-link new-resident-privacy-review" type="button" data-privacy-notice-open><span data-portal-i18n="Read the Data Privacy Notice">{{ __('Read the Data Privacy Notice') }}</span></button><button class="btn btn-green" type="submit"><span data-portal-i18n="Submit for barangay review">{{ __('Submit for barangay review') }}</span></button></div>
                </form>
            @endif
            <div class="new-resident-secondary-actions">
                <a href="{{ route('portal.register') }}"><span data-portal-i18n="Continue with an existing resident record">{{ __('Continue with an existing resident record') }}</span></a>
                @if($application)<a class="btn btn-outline" href="{{ route('portal.new-resident.create') }}"><span data-portal-i18n="Register another new resident">{{ __('Register another new resident') }}</span></a>@endif
            </div>
        </div>
    </section>
    <x-slot:help>
    <div class="rp-help-sections new-resident-aside">
        <section>
            <h2 id="new-resident-help-title"><span data-portal-i18n="What happens next">{{ __('What happens next') }}</span></h2>
            <ol class="new-resident-next-steps">
                @foreach(['Submit resident details', 'Barangay reviews your application', 'Complete portal account registration after approval', 'Receive an activation code'] as $step)<li><span data-portal-i18n="{{ $step }}">{{ __($step) }}</span></li>@endforeach
            </ol>
        </section>
        <section><h2><span data-portal-i18n="Already have a resident record?">{{ __('Already have a resident record?') }}</span></h2><p><span data-portal-i18n="Continue with an existing resident record">{{ __('Continue with an existing resident record') }}</span></p><a class="btn btn-outline btn-full" href="{{ route('portal.register') }}"><span data-portal-i18n="Create account">{{ __('Create account') }}</span></a></section>
    </div>
    </x-slot:help>
</x-portal.auth-shell>
@endsection
@push('scripts')
<script src="{{ asset('js/privacy-notice.js') }}?v={{ filemtime(public_path('js/privacy-notice.js')) }}"></script>
@endpush
