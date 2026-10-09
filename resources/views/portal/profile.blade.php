@extends('layouts.portal')
@section('title', __('Profile'))
@section('band')
<x-portal.page-band title="Profile">
    <p><span data-portal-i18n="Galing ang impormasyong ito sa Resident Records ng Barangay Anabu I-G. Puwede ninyong i-update ang inyong photo dito; barangay staff ang mag-aayos ng ibang detalye.">{{ __('Galing ang impormasyong ito sa Resident Records ng Barangay Anabu I-G. Puwede ninyong i-update ang inyong photo dito; barangay staff ang mag-aayos ng ibang detalye.') }}</span></p>
    <x-slot:actions>
        <a class="btn btn-outline" href="#corrections"><span data-portal-i18n="Paano magpa-correct">{{ __('Paano magpa-correct') }}</span></a>
        <a class="btn btn-green" href="#resident-photo"><span data-portal-i18n="Upload/Update Photo">{{ __('Upload/Update Photo') }}</span></a>
    </x-slot:actions>
</x-portal.page-band>
@endsection
@section('content')
<div class="profile-layout">
    <section class="panel record-sheet" aria-labelledby="record-title" data-resident-private>
        <div class="record-sheet-head">
            <h2 id="record-title"><span data-portal-i18n="Resident record">{{ __('Resident record') }}</span></h2>
            <p class="record-lock"><span data-portal-i18n="Hindi mae-edit online">{{ __('Hindi mae-edit online') }}</span></p>
        </div>
        <dl class="details-list">
            <dt><span data-portal-i18n="Resident number">{{ __('Resident number') }}</span></dt><dd class="resident-number-value">{{ $resident->resident_number }}</dd>
            <dt><span data-portal-i18n="Full name">{{ __('Full name') }}</span></dt><dd>{{ $resident->full_name }}</dd>
            <dt><span data-portal-i18n="Address">{{ __('Address') }}</span></dt><dd>{{ $resident->address }}</dd>
            <dt><span data-portal-i18n="Account email">{{ __('Account email') }}</span></dt><dd>{{ auth('resident')->user()->email }}</dd>
        </dl>
    </section>
    <div class="stack">
        <section class="panel" id="resident-photo" aria-labelledby="resident-photo-title" data-resident-private>
            <h2 id="resident-photo-title"><span data-portal-i18n="Resident photo">{{ __('Resident photo') }}</span></h2>
            @if($resident->has_photo)
                <p><img src="{{ route('portal.profile.photo') }}" width="150" height="180" style="object-fit: cover" data-portal-i18n-alt="Inyong kasalukuyang resident photo" alt="{{ __('Inyong kasalukuyang resident photo') }}"></p>
            @else
                <p><span data-portal-i18n="Wala pang resident photo. Mag-upload ng malinaw na larawan ng inyong mukha.">{{ __('Wala pang resident photo. Mag-upload ng malinaw na larawan ng inyong mukha.') }}</span></p>
            @endif
            <p><span data-portal-i18n="Ito ang larawan sa inyong Resident Record na ginagamit para sa mga susunod na certificate at clearance.">{{ __('Ito ang larawan sa inyong Resident Record na ginagamit para sa mga susunod na certificate at clearance.') }}</span></p>
            <form method="POST" action="{{ route('portal.profile.photo.store') }}" enctype="multipart/form-data" data-resident-form>
                @csrf
                <div class="form-group">
                    <label class="form-label" for="profile-photo"><span data-portal-i18n="Piliin ang larawan">{{ __('Piliin ang larawan') }}</span></label>
                    <input class="form-input" id="profile-photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required aria-describedby="profile-photo-help" @error('photo') aria-invalid="true" @enderror>
                    @error('photo')<p class="field-error" data-portal-message>{{ $message }}</p>@enderror
                    <p class="form-note" id="profile-photo-help"><span data-portal-i18n="JPG, PNG, o WebP; hanggang 5 MB. Piliin ulit ang larawan kung may error sa upload.">{{ __('JPG, PNG, o WebP; hanggang 5 MB. Piliin ulit ang larawan kung may error sa upload.') }}</span></p>
                </div>
                <button class="btn btn-green" type="submit"><span data-portal-i18n="{{ $resident->has_photo ? 'Update Photo' : 'Upload Photo' }}">{{ __($resident->has_photo ? 'Update Photo' : 'Upload Photo') }}</span></button>
            </form>
        </section>
        <section class="panel panel-tint corrections-panel" id="corrections" aria-labelledby="corrections-title">
            <h2 id="corrections-title"><span data-portal-i18n="May mali sa details?">{{ __('May mali sa details?') }}</span></h2>
            <p><span data-portal-i18n="Hindi mae-edit online ang resident record at account email. Para magpa-correct:">{{ __('Hindi mae-edit online ang resident record at account email. Para magpa-correct:') }}</span></p>
            <ol class="numbered-list">
                <li><span data-portal-i18n="Pumunta sa Barangay Hall ng Anabu I-G.">{{ __('Pumunta sa Barangay Hall ng Anabu I-G.') }}</span></li>
                <li><span data-portal-i18n="Dalhin ang valid ID. Itanong sa staff kung may iba pang kailangang dokumento.">{{ __('Dalhin ang valid ID. Itanong sa staff kung may iba pang kailangang dokumento.') }}</span></li>
                <li><span data-portal-i18n="Sabihin kung aling detalye ang mali at ano ang tama.">{{ __('Sabihin kung aling detalye ang mali at ano ang tama.') }}</span></li>
            </ol>
            <p class="small next-note"><span class="tag-unconfirmed"><span data-portal-i18n="Hindi pa kumpirmado">{{ __('Hindi pa kumpirmado') }}</span></span> <span data-portal-i18n="ang office hours ng barangay.">{{ __('ang office hours ng barangay.') }}</span></p>
        </section>
        <section class="panel" aria-labelledby="password-title">
            <h2 id="password-title"><span data-portal-i18n="Password">{{ __('Password') }}</span></h2>
            <p><span data-portal-i18n="Sa susunod na page, ilagay ang account email para makatanggap ng password reset link.">{{ __('Sa susunod na page, ilagay ang account email para makatanggap ng password reset link.') }}</span></p>
            <div class="btn-row"><a class="btn btn-outline" href="{{ route('password.request') }}"><span data-portal-i18n="Reset password">{{ __('Reset password') }}</span></a></div>
        </section>
    </div>
</div>
@endsection
