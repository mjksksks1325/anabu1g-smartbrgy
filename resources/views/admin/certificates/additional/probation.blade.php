{{--
    Follows "Brgy. Certificate-for Probation 2026.docx": page 1 word for word, and page 2 with the community-service
    photographs staff uploaded (served privately through staff.certificate-evidence.show).
--}}
@extends('admin.certificates.layout')

@section('styles')
    .probation .photo { left: 158.9mm; top: 58.7mm; width: 23.4mm; height: 27.9mm; }
    .probation-evidence .evidence-grid { position: absolute; left: 41.7mm; top: 67.6mm; width: 133.2mm; height: 175.6mm; display: grid; grid-template-columns: 1fr 1fr; grid-auto-rows: 1fr; gap: 3mm; }
    .probation-evidence .evidence-grid[data-photo-count="1"] { grid-template-columns: 1fr; }
    .probation-evidence .evidence-grid[data-photo-count="2"] { grid-template-rows: 1fr; }
    .probation-evidence .evidence-grid img { position: static; width: 100%; height: 100%; min-height: 0; object-fit: contain; object-position: center; }
@endsection

@section('sheets')
    <x-certificate.sheet :document="$document" label="Certificate for Probation" class="probation">
        <x-slot name="outside"><x-certificate.photo :document="$document" /></x-slot>
        <h1 class="spaced" aria-label="Certification">CERTIFICATION</h1>
        <p class="salutation">To Whom it may concern;</p>
        <p>This is to certify that the person whose photo, signature and thumb mark appear herein is a bona fide resident and registered voter of this barangay, completed her / his community service as of <strong>{{ \Carbon\Carbon::parse($document->extra('service_completed_on'))->format('F d, Y') }}.</strong></p>
        <p>This Certification is issued upon request of the above-mentioned person for any legal purposes it may serve.</p>
        @include('admin.certificates.partials.resident-details')
        <p class="issued">Issued this <x-certificate.day :date="$document->issuedAt()" /> at the office of Barangay Anabu I-G, City of Imus, Cavite.</p>
        @include('admin.certificates.partials.thumb-signature', ['signatureName' => $document->signatureName()])
    </x-certificate.sheet>

    {{-- Page 2: the community-service photographs, inside the template's picture area, never cropped. --}}
    @php($evidence = $document->snapshot()['evidence'] ?? [])
    <x-certificate.sheet :document="$document" label="Community service photographs" class="probation-evidence" :with-qr="false">
        <x-slot name="outside">
            <div class="evidence-grid" data-photo-count="{{ count($evidence) }}" data-keep-clear>
                @foreach ($evidence as $index => $uuid)
                    <img src="{{ route('staff.certificate-evidence.show', $uuid) }}" alt="Community service photograph {{ $index + 1 }} of {{ count($evidence) }}">
                @endforeach
            </div>
        </x-slot>
    </x-certificate.sheet>
@endsection
