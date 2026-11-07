{{--
    Follows "CERTIFICATE OF NO- OBJECTION 2026.docx" word for word: 20 pt title, 14 pt body on a wider text block
    (29.9 mm to 192.4 mm). The trees and their location are those staff recorded after the inspection the wording states.
--}}
@extends('admin.certificates.layout')

@php
    $count = (int) $document->extra('tree_count');
    $trees = sprintf('%02d', $count).' ('.\App\CertificateDocument::numberInWords($count).') '.\Illuminate\Support\Str::title((string) $document->extra('tree_species')).' '.($count === 1 ? 'Tree' : 'Trees');
@endphp

@section('styles')
    .no-objection .sheet-body { left: 29.9mm; right: 17.6mm; font-size: 14pt; line-height: 6.65mm; }
    .no-objection h1 { margin: 0 0 15.2mm; font-size: 20pt; line-height: 8.3mm; }
    .no-objection p { margin-bottom: 3.5mm; }
@endsection

@section('sheets')
    <x-certificate.sheet :document="$document" label="Certificate of No Objection" class="no-objection">
        <h1>CERTIFICATE OF NO OBJECTION</h1>
        <p>Upon inspection and verification of tree cutting certification request of <span class="fill upper">{{ strtoupper(\App\CertificateDocument::honorific($document->detail('gender'))) }} {{ $document->fullName() }}</span> with registered address at <span class="fill upper">{{ $document->address() }}</span>{{ $document->addressNamesBarangay() ? '' : ', Barangay Anabu I-G, City of Imus, Cavite' }}, this office considered the issuance of <strong>CERTIFICATE OF NO OBJECTION FOR THE CUTTING OF TREES</strong> on the following: <span class="fill">{{ $trees }}</span>, which found to be located in the {{ $document->extra('tree_location') }} and valid reason for cutting.</p>
        <p>This certification shall be valid upon certification of the <strong>CITY ENVIRONMENT AND NATURAL RESOURCES OFFICE (CENRO),</strong> City Government of IMUS.</p>
        <p class="issued">Issued this <x-certificate.day :date="$document->issuedAt()" /> at Barangay Anabu 1-G, City of Imus, Province of Cavite.</p>
    </x-certificate.sheet>
@endsection
