{{--
    Follows "Brgy. Certificate for Low-Income 2026.docx", word for word (including its stray "the t above"; a correction
    is proposed in the Work Group 6.1 report). Occupation and the estimated monthly income are what staff recorded.
--}}
@extends('admin.certificates.layout')

@section('styles')
    .low-income .photo { left: 158.9mm; top: 58.7mm; width: 23.4mm; height: 27.9mm; }
@endsection

@section('sheets')
    <x-certificate.sheet :document="$document" label="Certificate of Low Income" class="low-income">
        <x-slot name="outside"><x-certificate.photo :document="$document" /></x-slot>
        <h1 class="spaced" aria-label="Certification">CERTIFICATION</h1>
        <p class="salutation">To Whom it may concern:</p>
        <p>This is to certify that the person whose photo, signature and thumb mark appear herein is a <strong>bona fide resident and registered voter</strong> of this barangay. He/ She is of good moral character, a law-abiding citizen and has no derogatory record on file as of this date.</p>
        <p>This <strong>Barangay Certification of low income</strong> is issued upon request of the above-mentioned person for any legal purposes it may serve. Further certifies that the t above person mentioned belongs to the low-income earner family in our Barangay as a <strong class="upper">{{ $document->extra('occupation') }}</strong> with an estimated monthly income of P <strong>{{ number_format((float) $document->extra('monthly_income'), 2) }}.</strong></p>
        @include('admin.certificates.partials.resident-details')
        <p class="issued">Issued this <x-certificate.day :date="$document->issuedAt()" /> at the office of Barangay Anabu I-G, City of Imus, Cavite.</p>
        @include('admin.certificates.partials.thumb-signature', ['signatureName' => $document->signatureName()])
    </x-certificate.sheet>
@endsection
