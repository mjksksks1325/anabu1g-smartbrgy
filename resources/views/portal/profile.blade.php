@extends('layouts.portal')
@section('title', 'Profile')
@section('band')
<x-portal.page-band title="Profile">
    <p>Galing ang impormasyong ito sa Resident Records ng Barangay Anabu I-G. Puwede ninyong i-update ang inyong photo dito; barangay staff ang mag-aayos ng ibang detalye.</p>
    <x-slot:actions>
        <a class="btn btn-outline" href="#corrections">Paano magpa-correct</a>
        <a class="btn btn-green" href="#resident-photo">Upload/Update Photo</a>
    </x-slot:actions>
</x-portal.page-band>
@endsection
@section('content')
<div class="profile-layout">
    <section class="panel record-sheet" aria-labelledby="record-title" data-resident-private>
        <div class="record-sheet-head">
            <h2 id="record-title">Resident record</h2>
            <p class="record-lock">Hindi mae-edit online</p>
        </div>
        <dl class="details-list">
            <dt>Resident number</dt><dd class="resident-number-value">{{ $resident->resident_number }}</dd>
            <dt>Full name</dt><dd>{{ $resident->full_name }}</dd>
            <dt>Address</dt><dd>{{ $resident->address }}</dd>
            <dt>Account email</dt><dd>{{ auth('resident')->user()->email }}</dd>
        </dl>
    </section>
    <div class="stack">
        <section class="panel" id="resident-photo" aria-labelledby="resident-photo-title" data-resident-private>
            <h2 id="resident-photo-title">Resident photo</h2>
            @if($resident->has_photo)
                <p><img src="{{ route('portal.profile.photo') }}" width="150" height="180" style="object-fit: cover" alt="Inyong kasalukuyang resident photo"></p>
            @else
                <p>Wala pang resident photo. Mag-upload ng malinaw na larawan ng inyong mukha.</p>
            @endif
            <p>Ito ang larawan sa inyong Resident Record na ginagamit para sa mga susunod na certificate at clearance.</p>
            <form method="POST" action="{{ route('portal.profile.photo.store') }}" enctype="multipart/form-data" data-resident-form>
                @csrf
                <div class="form-group">
                    <label class="form-label" for="profile-photo">Piliin ang larawan</label>
                    <input class="form-input" id="profile-photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required aria-describedby="profile-photo-help" @error('photo') aria-invalid="true" @enderror>
                    @error('photo')<p class="field-error">{{ $message }}</p>@enderror
                    <p class="form-note" id="profile-photo-help">JPG, PNG, o WebP; hanggang 5 MB. Piliin ulit ang larawan kung may error sa upload.</p>
                </div>
                <button class="btn btn-green" type="submit">{{ $resident->has_photo ? 'Update Photo' : 'Upload Photo' }}</button>
            </form>
        </section>
        <section class="panel panel-tint corrections-panel" id="corrections" aria-labelledby="corrections-title">
            <h2 id="corrections-title">May mali sa details?</h2>
            <p>Hindi mae-edit online ang resident record at account email. Para magpa-correct:</p>
            <ol class="numbered-list">
                <li>Pumunta sa Barangay Hall ng Anabu I-G.</li>
                <li>Dalhin ang valid ID. Itanong sa staff kung may iba pang kailangang dokumento.</li>
                <li>Sabihin kung aling detalye ang mali at ano ang tama.</li>
            </ol>
            <p class="small next-note"><span class="tag-unconfirmed">Hindi pa kumpirmado</span> ang office hours ng barangay.</p>
        </section>
        <section class="panel" aria-labelledby="password-title">
            <h2 id="password-title">Password</h2>
            <p>Sa susunod na page, ilagay ang account email para makatanggap ng password reset link.</p>
            <div class="btn-row"><a class="btn btn-outline" href="{{ route('password.request') }}">Reset password</a></div>
        </section>
    </div>
</div>
@endsection
