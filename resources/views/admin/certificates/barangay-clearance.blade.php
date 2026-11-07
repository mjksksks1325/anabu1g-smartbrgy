{{-- No official Word template has been supplied for the Barangay Clearance; see components/certificate/clearance. --}}
@extends('admin.certificates.layout')

@section('sheets')
    <x-certificate.clearance :document="$document" title="BARANGAY CLEARANCE">
        <p>This is to certify that <strong>{{ $document->fullName() }}</strong> is a resident of Barangay Anabu I-G, City of Imus, Province of Cavite.</p>
        <p>This further certifies that based on the records available in this Barangay, the above-named person is being issued this Barangay Clearance upon request for <strong>{{ $document->purpose() }}</strong>.</p>
    </x-certificate.clearance>
@endsection
