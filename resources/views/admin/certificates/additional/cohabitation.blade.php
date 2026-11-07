{{--
    Follows "Brgy. Certificate for Common Law 2026.docx" word for word. The template's "Mr." and "Ms." blanks are
    filled from each resident's recorded gender; the years are those the couple declared to staff.
--}}
@extends('admin.certificates.layout')

@php
    $partner = $document->partner();
    $years = (int) $document->extra('years_together');
    $address = $document->address().($document->addressNamesBarangay() ? '' : ', Barangay Anabu I-G, City of Imus, Cavite');
@endphp

@section('styles')
    .cohabitation p.salutation { font-weight: bold; }
@endsection

@section('sheets')
    <x-certificate.sheet :document="$document" label="Certificate of Cohabitation" class="cohabitation">
        <h1 class="spaced" aria-label="Certification">CERTIFICATION</h1>
        <p class="salutation">To Whom It May Concern:</p>
        <p>This is to certify that {{ \App\CertificateDocument::honorific($document->detail('gender')) }} <span class="fill upper">{{ $document->fullName() }}</span> and {{ \App\CertificateDocument::honorific($partner['gender'] ?? null) }} <span class="fill upper">{{ $partner['full_name'] ?? '' }}</span>, both Filipino, of legal age, residents of <span class="fill">{{ $address }}</span>, depose and state that they have been living together as husband and wife under the same roof, continuously and without any interruption, a period of more than <strong>{{ \App\CertificateDocument::numberInWords($years) }} ({{ $years }}) years.</strong></p>
        <p>This <strong>Barangay Certification of Cohabitation</strong> is issued upon request of the above-mentioned person for <span class="fill upper">{{ $document->purpose() }}</span> purposes.</p>
        <p class="issued">Issued this <x-certificate.day :date="$document->issuedAt()" /> at the office of Barangay Anabu I-G, City of Imus, Cavite.</p>
    </x-certificate.sheet>
@endsection
