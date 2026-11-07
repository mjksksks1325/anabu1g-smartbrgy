{{--
    Follows "Brgy. Certificate-Residency-Senior 2026.docx": one sentence with the resident's details filled in on the
    template's underlined blanks. "and registered voter" is printed only when an active, untampered voter registration
    was on record at issuance; "since" is the month and year staff confirmed when issuing.
--}}
@extends('admin.certificates.layout')

@section('styles')
    .residency p.salutation, .residency p.purpose { margin-bottom: 9.9mm; }
@endsection

@section('sheets')
    <x-certificate.sheet :document="$document" label="Certificate of Residency" class="residency">
        <h1 class="spaced" aria-label="Certification">CERTIFICATION</h1>
        <p class="salutation">To Whom it may concern;</p>
        <p>This is to certify that Mr./ Mrs. <span class="fill upper">{{ $document->fullName() }}</span>, <span class="fill">{{ $document->ageAtIssue() }}</span> years old, <strong class="upper">{{ $document->detail('civil_status') }}</strong>, a bona fide resident{{ $document->voterVerified() ? ' and registered voter' : '' }} of <span class="fill upper">{{ $document->address() }}</span>{{ $document->addressNamesBarangay() ? '' : ' Barangay Anabu I-G, City of Imus Cavite' }} since <span class="fill upper">{{ $document->residingSince()?->format('F Y') }}</span>.</p>
        <p class="purpose">This Certification is issued upon request of the above-mentioned person for <span class="fill upper">{{ $document->purpose() }}</span> purposes it may serve.</p>
        <p class="issued">Issued this <x-certificate.day :date="$document->issuedAt()" /> at the office of Barangay Anabu I-G, City of Imus, Cavite.</p>
    </x-certificate.sheet>
@endsection
