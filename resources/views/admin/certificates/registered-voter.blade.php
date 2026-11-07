{{--
    Follows "Brgy. Certificate-bonafide-registered 2026.docx": spaced CERTIFICATION title, the template's wording, a blank
    right thumb mark box and the resident's signature line, whose name line sits at the template's 251.7 mm unless longer
    details need the room. Like the template, it prints no photo, although its wording speaks of "the person whose
    photo, signature and thumb mark appear herein"; that inconsistency awaits the barangay's decision, and issuance
    still requires a photo on record.
--}}
@extends('admin.certificates.layout')

@section('styles')
    .registered-voter p.salutation { margin-bottom: 9.9mm; }
@endsection

@section('sheets')
    <x-certificate.sheet :document="$document" label="Bona fide resident and registered voter certification" class="registered-voter">
        <h1 class="spaced" aria-label="Certification">CERTIFICATION</h1>
        <p class="salutation">To Whom it may concern;</p>
        <p>This is to certify that the person whose photo, signature and thumb mark appear herein is a bona fide resident and registered voter of this barangay. He/ She is of good moral character, a law-abiding citizen and has no derogatory record on file as of this date.</p>
        <p>This Certification is issued upon request of the above-mentioned person for any legal purposes it may serve.</p>
        @include('admin.certificates.partials.resident-details')
        <p class="issued">Issued this <x-certificate.day :date="$document->issuedAt()" /> at the office of Barangay Anabu I-G, City of Imus, Cavite.</p>
        <div class="thumb-mark">
            <div class="thumb-box" aria-hidden="true"></div>
            <span class="thumb-label">Right Thumb Mark</span>
        </div>
        <p class="resident-signature" data-reference-top="251.7"><strong>{{ $document->signatureName() }}</strong><span>Signature over Printed Name</span></p>
    </x-certificate.sheet>
@endsection
