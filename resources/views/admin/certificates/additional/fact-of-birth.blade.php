{{--
    Follows "Bgry. Certificate for Fact of Birth 2026.docx" word for word, including its wider text block (23.8 mm to
    201.5 mm). The parents and the child's details are those staff recorded; the selected resident is one parent.
--}}
@extends('admin.certificates.layout')

@section('styles')
    .fact-of-birth .sheet-body { left: 23.8mm; right: 8.5mm; }
    .fact-of-birth p.salutation { margin-bottom: 9.9mm; }
    .fact-of-birth .details th { font-weight: bold; width: 39.7mm; }
@endsection

@section('sheets')
    <x-certificate.sheet :document="$document" label="Certificate for Fact of Birth" class="fact-of-birth">
        <h1 class="spaced" aria-label="Certification">CERTIFICATION</h1>
        <p class="salutation">To Whom It May Concern;</p>
        <p>This is to certify that <strong>MR. <span class="fill upper">{{ $document->extra('father_name') }}</span> &amp; MS. <span class="fill upper">{{ $document->extra('mother_name') }}</span></strong> both of legal age, a registered voter of <span class="fill">{{ $document->address() }}</span>, <strong>Barangay Anabu 1-G, City of Imus, Cavite</strong> has given birth to his/her child without having them/his registered.</p>
        <x-certificate.details :rows="[
            ['Name of Child', $document->extra('child_name'), false],
            ['Date of Birth', filled($document->extra('child_birth_date')) ? \Carbon\Carbon::parse($document->extra('child_birth_date'))->format('F d, Y') : '', false],
            ['Place of Birth', $document->extra('child_birth_place'), false],
        ]" />
        <p>This certification is being issued upon the request of the above-named person for <span class="fill upper">{{ $document->purpose() }}</span> purposes it may serve.</p>
        <p>Given this <x-certificate.day :date="$document->issuedAt()" /> at Barangay Anabu 1-G, City of Imus, Cavite</p>
    </x-certificate.sheet>
@endsection
