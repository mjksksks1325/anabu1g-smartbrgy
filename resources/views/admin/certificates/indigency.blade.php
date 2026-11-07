{{-- Follows "Brgy. Indigency 2026.docx". The wording is the template's own; only the resident's details change. --}}
@extends('admin.certificates.layout')

@section('styles')
    .indigency .photo { left: 159.6mm; top: 56.4mm; width: 26.7mm; height: 28.4mm; }
@endsection

@section('sheets')
    <x-certificate.sheet :document="$document" label="Certification of Indigency" class="indigency">
        <x-slot name="outside"><x-certificate.photo :document="$document" /></x-slot>
        <h1>CERTIFICATION OF INDIGENCY</h1>
        <p class="salutation">To Whom it may concern:</p>
        <p>This is to certify that the person whose photo, signature and thumb mark appear herein is a registered voter of this barangay. He/ She is of good moral character, a law-abiding citizen and has no derogatory record on file as of this date.</p>
        <p>This is to certify further that the above-named belongs to an <strong>INDIGENT</strong> family of this barangay.</p>
        <p>This Certification is issued upon request of the above-mentioned person for any legal purposes it may serve.</p>
        @include('admin.certificates.partials.resident-details')
        <p class="issued">Issued this <x-certificate.day :date="$document->issuedAt()" /> at the office of Barangay Anabu I-G, City of Imus, Cavite.</p>
    </x-certificate.sheet>
@endsection
