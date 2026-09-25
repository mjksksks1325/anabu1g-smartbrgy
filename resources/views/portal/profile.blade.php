@extends('layouts.portal')
@section('title', 'Profile')
@section('band')
<x-portal.page-band title="Profile">
    <p>Galing ang impormasyong ito sa Resident Records ng Barangay Anabu I-G. Barangay staff lang ang puwedeng magbago nito.</p>
    <x-slot:actions>
        <a class="btn btn-outline" href="#corrections">Paano magpa-correct</a>
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
