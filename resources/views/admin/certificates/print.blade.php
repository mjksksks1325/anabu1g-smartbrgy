{{-- Fallback for a stored certificate type the system no longer recognises. --}}
@extends('admin.certificates.layout')

@section('sheets')
    <x-certificate.clearance :document="$document" :title="strtoupper((string) $certificate->certificate_type)">
        <p>This is to certify that <strong>{{ $document->fullName() }}</strong> is a resident of Barangay Anabu I-G, City of Imus, Province of Cavite.</p>
        <p>This certification is issued upon the request of the above-named resident for <strong>{{ $document->purpose() ?: 'whatever legal purpose it may serve' }}</strong>.</p>
    </x-certificate.clearance>
@endsection
