{{-- No official Word template has been supplied for the Business Clearance; see components/certificate/clearance. --}}
@extends('admin.certificates.layout')

@section('sheets')
    <x-certificate.clearance :document="$document" title="BUSINESS CLEARANCE">
        <p>This is to certify that <strong>{{ $document->fullName() }}</strong> has been issued this Barangay Business Clearance by Barangay Anabu I-G, City of Imus, Province of Cavite.</p>
        <p>This clearance is issued upon request for <strong>{{ $document->purpose() }}</strong>.</p>
    </x-certificate.clearance>
@endsection
