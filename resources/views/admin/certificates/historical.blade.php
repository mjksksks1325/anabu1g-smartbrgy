{{--
    Reprint of a certificate issued before the official 2026 templates (template_version null). Its wording and rows are
    those of the earlier template (admin/certificates/official.blade.php, unchanged from 4 October 2026 until it was
    replaced), filled only with the details recorded at issuance (resident_snapshot), so the reprint states exactly what
    the original did. It is set on the current one-page sheet; nothing is added, and no stand-in avatar is printed.
--}}
@extends('admin.certificates.layout')

@php
    $type = $document->type();
    $isIndigency = $type === \App\CertificateType::CertificateOfIndigency;
    $isVoter = $type === \App\CertificateType::RegisteredVoterCertification;
    $snapshot = $certificate->resident_snapshot ?? [];
    $notRecorded = collect(['address' => 'Address', 'date_of_birth' => 'Date of birth', 'gender' => 'Gender', 'civil_status' => 'Civil status', 'nationality' => 'Nationality'])
        ->filter(fn (string $label, string $key): bool => blank($snapshot[$key] ?? null))->values();
@endphp

@section('styles')
    .historical .photo { left: 159.6mm; top: 56.4mm; }
    .historical.voter p.salutation { margin-bottom: 4.95mm; }
    .historical .resident-signature { margin-top: 12.7mm; }
    .historical.fit-tight .resident-signature { margin-top: 7mm; }
    .historical .resident-signature strong { text-transform: none; }
@endsection

@section('notice')
    <p class="certificate-note" role="note">
        Reprint of {{ $certificate->certificate_number }}, issued {{ $certificate->issued_at?->format('F d, Y') }} with the earlier certificate template.
        It shows the wording and details recorded at issuance{{ $notRecorded->isNotEmpty() ? '; not recorded then, so left blank as on the original: '.$notRecorded->implode(', ') : '' }}.
        This reprint is recorded in the audit log.
    </p>
@endsection

@section('sheets')
    <x-certificate.sheet :document="$document" :label="'Reprint of '.$certificate->certificate_type" :class="$isVoter ? 'historical voter' : 'historical'">
        <x-slot name="outside"><x-certificate.photo :document="$document" /></x-slot>
        @if ($isVoter)
            <h1 class="spaced" aria-label="Certification">CERTIFICATION</h1>
        @else
            <h1>{{ $isIndigency ? 'CERTIFICATION OF INDIGENCY' : strtoupper($certificate->certificate_type) }}</h1>
        @endif
        <p class="salutation">To Whom it may concern{{ $isVoter ? ';' : ':' }}</p>
        @if ($isVoter || $isIndigency)
            <p>This is to certify that the person whose photo, signature and thumb mark appear herein is {{ $isIndigency ? 'a bona fide resident and registered voter' : 'a registered voter' }} of this barangay. He/ She is of good moral character, a law-abiding citizen and has no derogatory record on file as of this date.</p>
            @if ($isIndigency)
                <p>This is to certify further that the above-named belongs to an INDIGENT family of this barangay.</p>
            @endif
            <p>This Certification is issued upon request of the above-mentioned person for any legal purposes it may serve.</p>
        @else
            @switch ($type)
                @case (\App\CertificateType::BusinessClearance)
                    <p>This is to certify that <strong>{{ $certificate->resident_name }}</strong> has been issued this Barangay Business Clearance by Barangay Anabu I-G, City of Imus, Province of Cavite.</p>
                    <p>This clearance is issued upon request for <strong>{{ $certificate->purpose ?: 'business permit and other lawful purposes' }}</strong>.</p>
                    @break
                @case (\App\CertificateType::BarangayClearance)
                    <p>This is to certify that <strong>{{ $certificate->resident_name }}</strong> is a resident of Barangay Anabu I-G, City of Imus, Province of Cavite.</p>
                    <p>This further certifies that based on the records available in this Barangay, the above-named person is being issued this Barangay Clearance upon request for <strong>{{ $certificate->purpose ?: 'legal purposes' }}</strong>.</p>
                    @break
                @case (\App\CertificateType::FirstTimeJobseeker)
                    <p>This is to certify that <strong>{{ $certificate->resident_name }}</strong> is a bona fide resident of Barangay Anabu I-G, City of Imus, Province of Cavite.</p>
                    <p>This certification is issued in connection with the resident's application as a first-time jobseeker and for <strong>{{ $certificate->purpose ?: 'employment requirements' }}</strong>.</p>
                    @break
                @case (\App\CertificateType::CertificateOfResidency)
                    <p>This is to certify that <strong>{{ $certificate->resident_name }}</strong> is a bona fide resident of Barangay Anabu I-G, City of Imus, Province of Cavite.</p>
                    <p>This certification is issued upon the request of the above-named person for <strong>{{ $certificate->purpose ?: 'legal purposes' }}</strong>.</p>
                    @break
                @default
                    <p>This is to certify that <strong>{{ $certificate->resident_name }}</strong> is a resident of Barangay Anabu I-G, City of Imus, Province of Cavite.</p>
                    <p>This certification is issued upon the request of the above-named resident for <strong>{{ $certificate->purpose ?: 'whatever legal purpose it may serve' }}</strong>.</p>
            @endswitch
        @endif
        <x-certificate.details keep-blank :rows="[
            ['FULL', $certificate->resident_name, false],
            ['ADDRESS', $snapshot['address'] ?? '', false],
            ['DATE OF BIRTH', isset($snapshot['date_of_birth']) ? \Carbon\Carbon::parse($snapshot['date_of_birth'])->format('F d, Y') : '', false],
            ['GENDER', $snapshot['gender'] ?? '', false],
            ['CIVIL STATUS', $snapshot['civil_status'] ?? '', false],
            ['NATIONALITY', $snapshot['nationality'] ?? '', false],
            ['PURPOSE', $certificate->purpose ?? '', true],
            ['DATE ISSUE', $certificate->issued_at?->format('F d, Y') ?? '', true],
            ['EXPIRATION DATE', $certificate->validUntil()?->format('F d, Y') ?? '', false],
        ]" />
        <p class="issued">Issued this <x-certificate.day :date="$certificate->issued_at" /> at the office of Barangay Anabu I-G, City of Imus, Cavite.</p>
        @if ($isVoter)
            <div class="thumb-mark">
                <div class="thumb-box" aria-hidden="true"></div>
                <span class="thumb-label">Right Thumb Mark</span>
            </div>
            <p class="resident-signature"><strong>{{ $certificate->resident_name }}</strong><span>Signature over Printed Name</span></p>
        @elseif (! $isIndigency)
            <p class="official-signature"><strong>&nbsp;</strong><span>PUNONG BARANGAY</span></p>
        @endif
    </x-certificate.sheet>
@endsection
