@extends('layouts.portal')
@section('title', __('Profile'))
@section('band')
<x-portal.page-band title="Aking profile" parent="Aking account" :parent-url="route('portal.account.home')">
    <p><span data-portal-i18n="Galing ang impormasyong ito sa Resident Records ng Barangay Anabu I-G. Puwede ninyong i-update ang inyong photo dito; barangay staff ang mag-aayos ng ibang detalye.">{{ __('Galing ang impormasyong ito sa Resident Records ng Barangay Anabu I-G. Puwede ninyong i-update ang inyong photo dito; barangay staff ang mag-aayos ng ibang detalye.') }}</span></p>
</x-portal.page-band>
@endsection
@section('content')
<div class="profile-layout v2-profile">
    <section class="v2-card v2-record" aria-labelledby="record-title" data-resident-private>
        <div class="v2-record-head">
            @if($resident->has_photo)
                <img class="v2-avatar v2-avatar-large" src="{{ route('portal.profile.photo') }}" width="96" height="96" data-portal-i18n-alt="Inyong kasalukuyang resident photo" alt="{{ __('Inyong kasalukuyang resident photo') }}">
            @else
                <span class="v2-avatar v2-avatar-large" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span>
            @endif
            <div>
                <h2 id="record-title"><span data-portal-i18n="Resident record">{{ __('Resident record') }}</span></h2>
                <p class="record-lock"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg><span data-portal-i18n="Hindi mae-edit online">{{ __('Hindi mae-edit online') }}</span></p>
            </div>
        </div>
        <dl class="v2-details">
            <div><dt><span data-portal-i18n="Full name">{{ __('Full name') }}</span></dt><dd>{{ $resident->full_name }}</dd></div>
            <div><dt><span data-portal-i18n="Resident number">{{ __('Resident number') }}</span></dt><dd class="resident-number-value v2-ref">{{ $resident->resident_number }}</dd></div>
            <div><dt><span data-portal-i18n="Address">{{ __('Address') }}</span></dt><dd>{{ $resident->address }}</dd></div>
            <div><dt><span data-portal-i18n="Account email">{{ __('Account email') }}</span></dt><dd>{{ auth('resident')->user()->email }}</dd></div>
        </dl>
    </section>
    <div class="stack">
        <section class="v2-card" id="resident-photo" aria-labelledby="resident-photo-title" data-resident-private>
            <h2 id="resident-photo-title"><span data-portal-i18n="Resident photo">{{ __('Resident photo') }}</span></h2>
            @unless($resident->has_photo)
                <p><span data-portal-i18n="Wala pang resident photo. Mag-upload ng malinaw na larawan ng inyong mukha.">{{ __('Wala pang resident photo. Mag-upload ng malinaw na larawan ng inyong mukha.') }}</span></p>
            @endunless
            <p><span data-portal-i18n="Ito ang larawan sa inyong Resident Record na ginagamit para sa mga susunod na certificate at clearance.">{{ __('Ito ang larawan sa inyong Resident Record na ginagamit para sa mga susunod na certificate at clearance.') }}</span></p>
            <form method="POST" action="{{ route('portal.profile.photo.store') }}" enctype="multipart/form-data" data-resident-form>
                @csrf
                <div class="form-group">
                    <label class="form-label" for="profile-photo"><span data-portal-i18n="Piliin ang larawan">{{ __('Piliin ang larawan') }}</span></label>
                    <input class="form-input" id="profile-photo" data-photo-preview="profile-photo-preview" name="photo" type="file" accept=".jpg,.jpeg,.png,.gif,.bmp,.webp,.avif,.heic,.heif,image/jpeg,image/png,image/gif,image/bmp,image/webp,image/avif,image/heic,image/heif" data-resident-image-only data-error-target="profile-photo-client-error" required aria-describedby="profile-photo-help profile-photo-client-error" @error('photo') aria-invalid="true" @enderror>
                    <p class="field-error" id="profile-photo-client-error" hidden role="alert" aria-live="polite"><span data-portal-i18n="Choose an image file only (up to 5 MB). Documents are not accepted.">{{ __('Choose an image file only (up to 5 MB). Documents are not accepted.') }}</span></p>
                    @error('photo')<p class="field-error" data-portal-message>{{ $message }}</p>@enderror
                    <figure class="rp-photo-preview" id="profile-photo-preview" hidden><img alt="" width="120" height="120"><figcaption><span data-portal-i18n="Ito ang napiling larawan.">{{ __('Ito ang napiling larawan.') }}</span></figcaption></figure>
                        <p class="form-note" id="profile-photo-help"><span data-portal-i18n="Supported image files only, up to 5 MB. Documents are not accepted. Select the photo again if the upload fails.">{{ __('Supported image files only, up to 5 MB. Documents are not accepted. Select the photo again if the upload fails.') }}</span></p>
                </div>
                <button class="btn btn-green" type="submit"><span data-portal-i18n="{{ $resident->has_photo ? 'Update Photo' : 'Upload Photo' }}">{{ __($resident->has_photo ? 'Update Photo' : 'Upload Photo') }}</span></button>
            </form>
        </section>
        <section class="v2-card v2-card-info corrections-panel" id="corrections" aria-labelledby="corrections-title">
            <h2 id="corrections-title"><span data-portal-i18n="May mali sa details?">{{ __('May mali sa details?') }}</span></h2>
            <p><span data-portal-i18n="Hindi mae-edit online ang resident record at account email. Para magpa-correct:">{{ __('Hindi mae-edit online ang resident record at account email. Para magpa-correct:') }}</span></p>
            <ol class="numbered-list">
                <li><span data-portal-i18n="Pumunta sa Barangay Hall ng Anabu I-G.">{{ __('Pumunta sa Barangay Hall ng Anabu I-G.') }}</span></li>
                <li><span data-portal-i18n="Dalhin ang valid ID. Itanong sa staff kung may iba pang kailangang dokumento.">{{ __('Dalhin ang valid ID. Itanong sa staff kung may iba pang kailangang dokumento.') }}</span></li>
                <li><span data-portal-i18n="Sabihin kung aling detalye ang mali at ano ang tama.">{{ __('Sabihin kung aling detalye ang mali at ano ang tama.') }}</span></li>
            </ol>
            <p class="small next-note"><span class="tag-unconfirmed"><span data-portal-i18n="Hindi pa kumpirmado">{{ __('Hindi pa kumpirmado') }}</span></span> <span data-portal-i18n="ang office hours ng barangay.">{{ __('ang office hours ng barangay.') }}</span></p>
        </section>
        <section class="v2-card" aria-labelledby="password-title">
            <h2 id="password-title"><span data-portal-i18n="Password">{{ __('Password') }}</span></h2>
            <p><span data-portal-i18n="Sa susunod na page, ilagay ang account email para makatanggap ng password reset link.">{{ __('Sa susunod na page, ilagay ang account email para makatanggap ng password reset link.') }}</span></p>
            <div class="btn-row"><a class="btn btn-outline" href="{{ route('password.request', ['from' => 'portal']) }}"><span data-portal-i18n="Reset password">{{ __('Reset password') }}</span></a></div>
        </section>
    </div>
</div>
@endsection
