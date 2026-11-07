{{--
    Follows "Brgy. Certificate-NATIONAL ID.docx" word for word, with its right-aligned labels. Place of birth and the
    parents' names are those staff recorded.
--}}
@extends('admin.certificates.layout')

@section('styles')
    .national-id .photo { left: 154.2mm; top: 56.1mm; width: 28.2mm; height: 32.8mm; }
    .national-id .details th { width: 50.8mm; padding-right: 1.6mm; text-align: right; }
@endsection

@section('sheets')
    <x-certificate.sheet :document="$document" label="Certificate for National ID Registration" class="national-id">
        <x-slot name="outside"><x-certificate.photo :document="$document" /></x-slot>
        <h1 class="spaced" aria-label="Certification">CERTIFICATION</h1>
        <p class="salutation">To Whom it may concern;</p>
        <p>This is to certify that the person whose photo, signature and thumb mark appear herein is a bona fide resident and registered voter of this barangay. He/ She is of good moral character, a law-abiding citizen and has no derogatory record on file as of this date.</p>
        <p>This <strong>Barangay Certification</strong> is issued upon request of the above-mentioned person for any legal purposes it may serve.</p>
        <x-certificate.details :rows="[
            ['FULL', $document->fullName(), false],
            ['DATE OF BIRTH', $document->birthDate()?->format('F d, Y'), false],
            ['BIRTH PLACE', $document->extra('birth_place'), false],
            ['ADDRESS', $document->address(), false],
            ['FULL NAME OF MOTHER', $document->extra('mother_name'), false],
            ['FULL NAME OF FATHER', $document->extra('father_name'), false],
            ['PURPOSE', $document->purpose(), true],
            ['DATE ISSUE', $document->issuedAt()?->format('F d, Y'), true],
            ['EXPIRATION DATE', $document->validUntil()?->format('F d, Y'), false],
        ]" />
        <p class="issued">Issued this <x-certificate.day :date="$document->issuedAt()" /> at the office of Barangay Anabu I-G, City of Imus, Cavite.</p>
        @include('admin.certificates.partials.thumb-signature', ['signatureName' => $document->signatureName(), 'referenceTop' => '255.1'])
    </x-certificate.sheet>
@endsection
