@php
    $documentType = \App\CertificateType::tryFromLabel($certificate->certificate_type);
    $isIndigency = $documentType === \App\CertificateType::CertificateOfIndigency;
    $isVoter = $documentType === \App\CertificateType::RegisteredVoterCertification;
    $snapshot = $certificate->resident_snapshot ?? [];
    $birthDate = isset($snapshot['date_of_birth']) ? \Carbon\Carbon::parse($snapshot['date_of_birth'])->format('F d, Y') : '';
    $fields = [
        'FULL' => $certificate->resident_name,
        'ADDRESS' => $snapshot['address'] ?? '',
        'DATE OF BIRTH' => $birthDate,
        'GENDER' => $snapshot['gender'] ?? '',
        'CIVIL STATUS' => $snapshot['civil_status'] ?? '',
        'NATIONALITY' => $snapshot['nationality'] ?? '',
        'PURPOSE' => $certificate->purpose ?? '',
        'DATE ISSUE' => $certificate->issued_at?->format('F d, Y') ?? '',
        'EXPIRATION DATE' => $certificate->expires_on?->format('F d, Y') ?? '',
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $certificate->certificate_type }}</title>
    <style>
        @page { size: A4; margin: 0; }
        body { margin: 0; background: #e5e7eb; color: #111; font-family: 'Times New Roman', serif; }
        .official-certificate { position: relative; display: flex; flex-direction: column; box-sizing: border-box; width: 210mm; min-height: 297mm; padding: 57mm 25.4mm 12.7mm; margin: 16px auto; background: white; }
        .official-certificate * { box-sizing: border-box; }
        .official-certificate header { position: absolute; top: 12.7mm; left: 0; width: 100%; height: 41mm; text-align: center; }
        .official-certificate header::after { content: ''; position: absolute; top: 40.3mm; left: 31.75mm; right: 15.875mm; border-bottom: 1pt solid #111; }
        .official-certificate .seal { position: absolute; top: 2mm; object-fit: contain; }
        .official-certificate .imus-seal { left: 9.5mm; width: 29.4mm; height: 27.8mm; }
        .official-certificate .anabu-seal { left: 36.25mm; width: 29.4mm; height: 27.8mm; }
        .official-certificate .national-seal { left: 163.5mm; width: 26.85mm; height: 25.7mm; }
        .official-certificate .government { position: absolute; top: 5.5mm; left: 65mm; width: 80mm; font-size: 12pt; line-height: 14pt; }
        .official-certificate .government strong { display: block; }
        .official-certificate .government .office { margin-top: 6mm; }
        .official-certificate .title-area { position: relative; min-height: 22mm; }
        .official-certificate h1 { text-align: center; font-size: 18pt; line-height: 21pt; margin: 0; padding-top: 6mm; }
        .official-certificate.voter h1 { text-indent: 12.7mm; }
        .official-certificate .photo { position: absolute; top: .6mm; right: 2.5mm; width: 25.2mm; height: 30.3mm; border: 1px solid #111; object-fit: contain; }
        .official-certificate.indigency .photo { width: 23.8mm; height: 26.4mm; }
        .official-certificate p { font-size: 12pt; line-height: 14pt; margin: 0 0 14pt; text-align: justify; text-indent: 12.7mm; }
        .official-certificate .salutation { text-indent: 0; }
        .official-certificate table { table-layout: fixed; border-collapse: collapse; width: 100%; margin: 0; font-size: 12pt; line-height: 14pt; }
        .official-certificate th { text-align: left; vertical-align: top; width: 38.1mm; font-weight: normal; padding: 0; white-space: nowrap; }
        .official-certificate td { padding: 0; font-weight: bold; text-transform: uppercase; overflow-wrap: anywhere; vertical-align: top; }
        .official-certificate .section-start th, .official-certificate .section-start td { padding-top: 14pt; }
        .official-certificate tr { break-inside: avoid; }
        .official-certificate .issue-statement { margin: 14pt 0 0; }
        .official-certificate .document-bottom { display: grid; grid-template-columns: 70mm 1fr; grid-template-rows: minmax(auto, 1fr) 24mm; column-gap: 4mm; flex: 1; min-height: 75mm; padding-top: 6.35mm; break-inside: avoid; }
        .official-certificate .thumb-area { width: 25.4mm; text-align: center; }
        .official-certificate .thumb { border: 1px solid #111; border-radius: 4.5mm; height: 25.4mm; width: 25.4mm; }
        .official-certificate .thumb-label { display: block; font-size: 8pt; font-style: italic; padding-top: 2mm; }
        .official-certificate .signature { margin-top: 12mm; padding-bottom: 8mm; font-size: 12pt; }
        .official-certificate .signature strong { text-decoration: underline; }
        .official-certificate .signature span { display: block; font-size: 10pt; font-style: italic; }
        .official-certificate .authorizing-signature { padding-top: 25mm; font-size: 12pt; }
        .official-certificate .authorizing-signature span { display: inline-block; border-top: 1px solid #111; padding-top: 2mm; min-width: 55mm; text-align: center; }
        .official-certificate footer { grid-column: 1 / -1; grid-row: 2; align-self: end; display: flex; align-items: flex-end; justify-content: space-between; gap: 6mm; margin-right: -12.7mm; break-inside: avoid; }
        .official-certificate .verification { max-width: 45mm; font: 7pt/1.2 Arial, sans-serif; overflow-wrap: anywhere; }
        .official-certificate .qr { display: block; width: 20mm; height: 20mm; margin-bottom: 1mm; }
        .official-certificate .imus-footer { width: 29.4mm; height: 37.5mm; object-fit: contain; }
        .certificate-controls { text-align: center; padding: 12px; font-family: Arial, sans-serif; }
        .certificate-controls a { margin-right: 16px; }
        @media print {
            body { background: white; }
            .certificate-controls { display: none; }
            .official-certificate { margin: 0; }
        }
    </style>
</head>
<body>
<nav class="certificate-controls" aria-label="Certificate navigation">
    <a href="{{ route('admin.document-requests.index') }}">Back to certificates</a>
    <button type="button" onclick="window.print()">Print certificate</button>
</nav>
<main class="official-certificate {{ $isIndigency ? 'indigency' : ($isVoter ? 'voter' : 'standard') }}">
    <header>
        <img class="seal imus-seal" src="{{ asset('images/certificates/imus-seal.png') }}" alt="City of Imus seal">
        <img class="seal anabu-seal" src="{{ asset('images/certificates/anabu-source-seal.png') }}" alt="Barangay Anabu I-G seal">
        <div class="government">Republic of the Philippines<br>Province of Cavite<br>City of Imus<strong class="office">OFFICE OF THE SANGGUNIANG BARANGAY</strong><strong>BARANGAY ANABU I-G</strong></div>
        <img class="seal national-seal" src="{{ asset('images/certificates/bagong-pilipinas.png') }}" alt="Bagong Pilipinas">
    </header>
    <div class="title-area">
        <h1>{{ $isVoter ? 'C E R T I F I C A T I O N' : ($isIndigency ? 'CERTIFICATION OF INDIGENCY' : strtoupper($certificate->certificate_type)) }}</h1>
        <img class="photo" src="{{ $certificate->photo_path ? route('admin.issued-certificates.photo', $certificate) : asset('images/official-placeholder.svg') }}" alt="{{ $certificate->photo_path ? 'Resident photo at issuance' : 'Resident photo not provided' }}">
    </div>
    <p class="salutation">To Whom it may concern{{ $isVoter ? ';' : ':' }}</p>
    @if ($isVoter || ($isIndigency && $certificate->resident_snapshot !== null))
        <p>This is to certify that the person whose photo, signature and thumb mark appear herein is {{ $isIndigency ? 'a bona fide resident and registered voter' : 'a registered voter' }} of this barangay. He/ She is of good moral character, a law-abiding citizen and has no derogatory record on file as of this date.</p>
        @if ($isIndigency)
            <p>This is to certify further that the above-named belongs to an INDIGENT family of this barangay.</p>
        @endif
        <p>This Certification is issued upon request of the above-mentioned person for any legal purposes it may serve.</p>
    @else
        @switch ($documentType)
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
            @case (\App\CertificateType::CertificateOfIndigency)
                <p>This is to certify that <strong>{{ $certificate->resident_name }}</strong> is a bona fide resident of Barangay Anabu I-G, City of Imus, Province of Cavite.</p>
                <p>This further certifies that the above-named resident is requesting this Certificate of Indigency for <strong>{{ $certificate->purpose ?: 'assistance and other lawful purposes' }}</strong>.</p>
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
    <table aria-label="Resident details at issuance">
        @foreach ($fields as $label => $value)
            <tr @class(['section-start' => in_array($label, ['PURPOSE', 'DATE ISSUE'], true)])><th>{{ $label }}:</th><td>{{ $value }}</td></tr>
        @endforeach
    </table>
    <p class="issue-statement">Issued this <strong>{{ $certificate->issued_at?->format('d') }}<sup>{{ $certificate->issued_at?->format('S') }}</sup></strong> day of <strong>{{ $certificate->issued_at?->format('F, Y') }}</strong> at the office of Barangay Anabu I-G, City of Imus, Cavite.</p>
    <div class="document-bottom">
        <div>
            @if ($isVoter)
                <div class="thumb-area"><div class="thumb"></div><span class="thumb-label">Right Thumb Mark</span></div>
                <div class="signature"><strong>{{ $certificate->resident_name }}</strong><span>Signature over Printed Name</span></div>
            @elseif (! $isIndigency || $certificate->resident_snapshot === null)
                <div class="authorizing-signature"><span>PUNONG BARANGAY</span></div>
            @endif
        </div>
        <footer>
            <div class="verification">
                @if ($certificate->qr_code_path)<img class="qr" src="{{ asset($certificate->qr_code_path) }}" alt="Certificate authenticity QR code">@endif
                <div>Scan QR code to verify</div>
            </div>
            <img class="imus-footer" src="{{ asset('images/certificates/imus-footer.png') }}" alt="City of Imus footer logo">
        </footer>
    </div>
</main>
</body>
</html>
