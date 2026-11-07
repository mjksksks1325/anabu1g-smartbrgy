@extends('layouts.portal')
@section('title', __('Aking account'))
@section('band')
<x-portal.page-band title="Aking account">
    <p><span data-portal-i18n="Ang inyong impormasyon bilang residente, ang inyong photo, at ang seguridad ng account.">{{ __('Ang inyong impormasyon bilang residente, ang inyong photo, at ang seguridad ng account.') }}</span></p>
</x-portal.page-band>
@endsection
@section('content')
<div class="rp-account">
    <div class="rp-account-col">
        <section class="rp-panel" aria-labelledby="record-title" data-resident-private>
            <div class="rp-account-identity">
                @if($resident->has_photo)
                    <img class="v2-avatar rp-account-avatar" src="{{ route('portal.profile.photo') }}" width="88" height="88" data-portal-i18n-alt="Inyong kasalukuyang resident photo" alt="{{ __('Inyong kasalukuyang resident photo') }}">
                @else
                    <span class="v2-avatar rp-account-avatar" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span>
                @endif
                <div>
                    <h2 id="record-title">{{ $resident->full_name }}</h2>
                    <p class="rp-account-number"><span data-portal-i18n="Resident number">{{ __('Resident number') }}</span> <span class="resident-number-value v2-ref">{{ $resident->resident_number }}</span></p>
                </div>
            </div>
            <dl class="rp-info-list">
                <div><dt><span data-portal-i18n="Address">{{ __('Address') }}</span></dt><dd>{{ $resident->address }}</dd></div>
                <div><dt><span data-portal-i18n="Account email">{{ __('Account email') }}</span></dt><dd>{{ auth('resident')->user()->email }}</dd></div>
            </dl>
            <p class="record-lock"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg><span data-portal-i18n="Galing sa Resident Records ng barangay. Hindi ito mae-edit online.">{{ __('Galing sa Resident Records ng barangay. Hindi ito mae-edit online.') }}</span></p>
            <details class="rp-corrections" id="corrections">
                <summary><span data-portal-i18n="May mali sa details?">{{ __('May mali sa details?') }}</span></summary>
                <ol class="numbered-list">
                    <li><span data-portal-i18n="Pumunta sa Barangay Hall ng Anabu I-G.">{{ __('Pumunta sa Barangay Hall ng Anabu I-G.') }}</span></li>
                    <li><span data-portal-i18n="Dalhin ang valid ID. Itanong sa staff kung may iba pang kailangang dokumento.">{{ __('Dalhin ang valid ID. Itanong sa staff kung may iba pang kailangang dokumento.') }}</span></li>
                    <li><span data-portal-i18n="Sabihin kung aling detalye ang mali at ano ang tama.">{{ __('Sabihin kung aling detalye ang mali at ano ang tama.') }}</span></li>
                </ol>
                <p class="v2-summary-note"><span data-portal-i18n="Office hours:">{{ __('Office hours:') }}</span> <span class="tag-unconfirmed"><span data-portal-i18n="Hindi pa kumpirmado">{{ __('Hindi pa kumpirmado') }}</span></span></p>
            </details>
        </section>
    </div>

    <div class="rp-account-col">
        <section class="rp-panel" id="resident-photo" aria-labelledby="resident-photo-title" data-resident-private>
            <h2 id="resident-photo-title"><span data-portal-i18n="Resident photo">{{ __('Resident photo') }}</span></h2>
            <p class="v2-summary-note"><span data-portal-i18n="Ito ang larawan sa inyong Resident Record na ginagamit para sa mga susunod na certificate at clearance.">{{ __('Ito ang larawan sa inyong Resident Record na ginagamit para sa mga susunod na certificate at clearance.') }}</span></p>
            @unless($resident->has_photo)
                <p class="rp-note-warning"><span data-portal-i18n="Wala pang resident photo. Mag-upload ng malinaw na larawan ng inyong mukha.">{{ __('Wala pang resident photo. Mag-upload ng malinaw na larawan ng inyong mukha.') }}</span></p>
            @endunless
            <form method="POST" action="{{ route('portal.profile.photo.store') }}" enctype="multipart/form-data" data-resident-form>
                @csrf
                <div class="form-group">
                    <label class="form-label" for="profile-photo"><span data-portal-i18n="Piliin ang larawan">{{ __('Piliin ang larawan') }}</span></label>
                    <input class="form-input" id="profile-photo" data-photo-preview="profile-photo-preview" name="photo" type="file" accept=".jpg,.jpeg,.png,.gif,.bmp,.webp,.avif,.heic,.heif,image/jpeg,image/png,image/gif,image/bmp,image/webp,image/avif,image/heic,image/heif" data-resident-image-only data-error-target="profile-photo-client-error" required aria-describedby="profile-photo-help profile-photo-client-error" @error('photo') aria-invalid="true" @enderror>
                    <p class="field-error" id="profile-photo-client-error" hidden role="alert" aria-live="polite"><span data-portal-i18n="Choose an image file only (up to 10 MB). Documents are not accepted.">{{ __('Choose an image file only (up to 10 MB). Documents are not accepted.') }}</span></p>
                    @error('photo')<p class="field-error" data-portal-message>{{ $message }}</p>@enderror
                    <figure class="rp-photo-preview" id="profile-photo-preview" hidden><img alt="" width="120" height="120"><figcaption><span data-portal-i18n="Ito ang napiling larawan.">{{ __('Ito ang napiling larawan.') }}</span></figcaption></figure>
                    <p class="form-note" id="profile-photo-help"><span data-portal-i18n="JPG, PNG, WEBP, HEIC at iba pang larawan, hanggang 10 MB. Hindi tinatanggap ang PDF o ibang dokumento.">{{ __('JPG, PNG, WEBP, HEIC at iba pang larawan, hanggang 10 MB. Hindi tinatanggap ang PDF o ibang dokumento.') }}</span></p>
                </div>
                <button class="btn btn-green" type="submit"><span data-portal-i18n="{{ $resident->has_photo ? 'Update Photo' : 'Upload Photo' }}">{{ __($resident->has_photo ? 'Update Photo' : 'Upload Photo') }}</span></button>
            </form>
        </section>

        <section class="rp-panel" aria-labelledby="password-title">
            <h2 id="password-title"><span data-portal-i18n="Password at seguridad">{{ __('Password at seguridad') }}</span></h2>
            <p class="v2-summary-note"><span data-portal-i18n="Para palitan ang password, magpapadala kami ng link sa inyong account email. Buksan ang link at gumawa ng bagong password.">{{ __('Para palitan ang password, magpapadala kami ng link sa inyong account email. Buksan ang link at gumawa ng bagong password.') }}</span></p>
            <a class="btn btn-outline" href="{{ route('password.request', ['from' => 'portal']) }}"><span data-portal-i18n="Palitan ang password">{{ __('Palitan ang password') }}</span></a>
        </section>
    </div>
</div>
@endsection
