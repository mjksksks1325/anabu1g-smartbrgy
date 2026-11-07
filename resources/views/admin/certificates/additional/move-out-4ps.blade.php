{{--
    Follows "Brgy.Certificate-MOVE OUT FOR 4PS 2026.docx" word for word. Its "her beneficiary" blank is filled from the
    recorded gender (her / his); the destination is the address staff recorded.
--}}
@extends('admin.certificates.layout')

@php($pronoun = strtolower((string) $document->detail('gender')) === 'male' ? 'his' : 'her')

@section('styles')
    .move-out .photo { left: 151.8mm; top: 55.8mm; width: 23.6mm; height: 29.5mm; }
@endsection

@section('sheets')
    <x-certificate.sheet :document="$document" label="Certificate of Move-Out for 4Ps" class="move-out">
        <x-slot name="outside"><x-certificate.photo :document="$document" /></x-slot>
        <h1 class="spaced" aria-label="Certification">CERTIFICATION</h1>
        <p class="salutation">To Whom it may concern:</p>
        <p>This is to certify that the person whose photo, signature and thumb mark appear herein is a registered voter of this barangay. He/ She is of good moral character, a law abiding citizen and has no derogatory record on file as of this date.</p>
        <p>This <strong>Barangay Certification of MOVE-OUT</strong> is issued upon request of the above-mentioned person for transferring {{ $pronoun }} beneficiary as a 4Ps beneficiary from <strong>Barangay Anabu I-G, City of Imus, Cavite</strong> to <strong>{{ $document->extra('destination') }}.</strong></p>
        <x-certificate.details :rows="[
            ['FULL NAME', $document->fullName(), false],
            ['DATE OF BIRTH', $document->birthDate()?->format('F d, Y'), false],
            ['GENDER', $document->detail('gender'), false],
            ['CIVIL STATUS', $document->detail('civil_status'), false],
            ['NATIONALITY', $document->detail('nationality'), false],
            ['PURPOSE', $document->purpose(), true],
        ]" />
        <p class="issued">Issued this <x-certificate.day :date="$document->issuedAt()" /> at the office of Barangay Anabu I-G, City of Imus, Cavite.</p>
        @include('admin.certificates.partials.thumb-signature', ['signatureName' => $document->fullName(), 'referenceTop' => '240.9'])
    </x-certificate.sheet>
@endsection
