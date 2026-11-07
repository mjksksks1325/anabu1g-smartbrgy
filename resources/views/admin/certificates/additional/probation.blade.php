{{--
    Page 1 of "Brgy. Certificate-for Probation 2026.docx", word for word. The template's second page of photographs of
    the community service is not produced yet (no evidence upload exists); see the Work Group 6.1 report.
--}}
@extends('admin.certificates.layout')

@section('styles')
    .probation .photo { left: 158.9mm; top: 58.7mm; width: 23.4mm; height: 27.9mm; }
@endsection

@section('sheets')
    <x-certificate.sheet :document="$document" label="Certificate for Probation" class="probation">
        <x-slot name="outside"><x-certificate.photo :document="$document" /></x-slot>
        <h1 class="spaced" aria-label="Certification">CERTIFICATION</h1>
        <p class="salutation">To Whom it may concern;</p>
        <p>This is to certify that the person whose photo, signature and thumb mark appear herein is a bona fide resident and registered voter of this barangay, completed her / his community service as of <strong>{{ \Carbon\Carbon::parse($document->extra('service_completed_on'))->format('F d, Y') }}.</strong></p>
        <p>This Certification is issued upon request of the above-mentioned person for any legal purposes it may serve.</p>
        @include('admin.certificates.partials.resident-details')
        <p class="issued">Issued this <x-certificate.day :date="$document->issuedAt()" /> at the office of Barangay Anabu I-G, City of Imus, Cavite.</p>
        @include('admin.certificates.partials.thumb-signature', ['signatureName' => $document->signatureName()])
    </x-certificate.sheet>
@endsection
