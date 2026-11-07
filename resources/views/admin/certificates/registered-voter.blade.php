{{--
    Follows "Brgy. Certificate-bonafide-registered 2026.docx": spaced CERTIFICATION title, the template's wording, a blank
    right thumb mark box and the resident's signature line. The wording refers to the resident's photo, so the photo
    taken at issuance sits beside the title as on the templates that show one (Probation, 4Ps Move-Out, National ID).
--}}
@extends('admin.certificates.layout')

@section('styles')
    .registered-voter .photo { left: 158.9mm; top: 58.7mm; width: 23.4mm; height: 27.9mm; }
    .registered-voter p.salutation { margin-bottom: 9.9mm; }
@endsection

@section('sheets')
    <x-certificate.sheet :document="$document" label="Bona fide resident and registered voter certification" class="registered-voter">
        <x-slot name="outside">
            <x-certificate.photo :document="$document" />
        </x-slot>
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
        <p class="resident-signature"><strong>{{ $document->signatureName() }}</strong><span>Signature over Printed Name</span></p>
    </x-certificate.sheet>
@endsection
