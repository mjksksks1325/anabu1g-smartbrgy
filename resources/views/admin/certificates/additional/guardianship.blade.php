{{-- Follows "Brgy. Certificate of Guardianship 2026.docx" word for word; the dependents are those staff recorded. --}}
@extends('admin.certificates.layout')

@php
    $dependents = collect($document->extra('dependents') ?? [])->map(fn ($name) => e(mb_strtoupper((string) $name)));
    $dependentList = $dependents->count() > 1 ? $dependents->slice(0, -1)->map(fn ($name) => '<span class="fill">'.$name.'</span>')->implode(', ').' and <span class="fill">'.$dependents->last().'</span>' : '<span class="fill">'.$dependents->first().'</span>';
    $address = $document->address().($document->addressNamesBarangay() ? '' : ', Barangay Anabu I-G, City of Imus, Cavite');
@endphp

@section('sheets')
    <x-certificate.sheet :document="$document" label="Certificate of Guardianship" class="guardianship">
        <h1 class="spaced" aria-label="Certification">CERTIFICATION</h1>
        <p class="salutation">To Whom It May Concern:</p>
        <p>This is to certify that <span class="fill upper">{{ $document->fullName() }}</span>, of legal age, and a resident of <span class="fill upper">{{ $address }}</span> is the guardian/caregiver of {!! $dependentList !!}, who is currently residing at the same address in Barangay Anabu 1-G, City of Imus Cavite.</p>
        <p>This Certification of Guardianship is issued upon request of the above-mentioned for <strong class="upper">{{ $document->purpose() }}</strong>.</p>
        <p class="issued">Issued this <x-certificate.day :date="$document->issuedAt()" /> at the office of Barangay Anabu I-G, City of Imus, Cavite.</p>
    </x-certificate.sheet>
@endsection
