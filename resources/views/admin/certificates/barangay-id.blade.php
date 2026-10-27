<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Barangay Resident ID</title>
    <style>
        @page { size: 85.6mm 54mm; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e7eb; color: #111; font-family: 'Times New Roman', serif; }
        .id-card { position: relative; width: 85.6mm; height: 54mm; margin: 24px auto; padding: 2mm; overflow: hidden; background: #fff; border: .45mm solid #163650; border-radius: 1.5mm; }
        .id-card::before { position: absolute; top: 0; left: 0; width: 100%; height: 1.2mm; background: #163650; content: ''; }
        .id-header { display: flex; height: 11.3mm; align-items: center; justify-content: center; gap: 1.2mm; padding: 1mm 1mm .8mm; border-bottom: .25mm solid #b48a35; text-align: center; }
        .seal { flex: 0 0 auto; width: 8mm; height: 8mm; object-fit: contain; }
        .header-text { flex: 1; min-width: 0; font-size: 4.4pt; line-height: 1.1; }
        .header-text .office { display: block; margin-top: .5mm; font-size: 4pt; font-weight: bold; }
        .barangay-name { display: block; font-size: 5pt; font-weight: bold; }
        .id-title { display: block; margin-top: .45mm; color: #163650; font-size: 5.2pt; font-weight: bold; letter-spacing: .1pt; }
        .id-body { display: flex; gap: 2mm; padding: 1.6mm .7mm 0; }
        .photo { flex: 0 0 auto; width: 18mm; height: 21mm; border: .25mm solid #163650; object-fit: cover; }
        .details { min-width: 0; flex: 1; padding-top: .2mm; font-size: 5.5pt; line-height: 1.18; }
        .name { display: block; max-height: 7mm; margin-bottom: 1mm; overflow: hidden; color: #163650; font-size: 8pt; font-weight: bold; line-height: 1.05; text-transform: uppercase; overflow-wrap: anywhere; }
        .field { display: flex; gap: 1mm; margin-top: .65mm; }
        .field strong { flex: 0 0 auto; font-weight: bold; }
        .field span { min-width: 0; overflow-wrap: anywhere; }
        .id-footer { position: absolute; right: 2mm; bottom: 1.6mm; left: 2.7mm; display: flex; height: 12mm; align-items: flex-end; justify-content: space-between; }
        .verification { align-self: flex-end; max-width: 43mm; padding-bottom: 1mm; font: 4.5pt/1.2 Arial, sans-serif; }
        .qr { display: block; width: 12mm; height: 12mm; margin-bottom: .5mm; }
        .imus-footer { width: 8.5mm; height: 10.5mm; object-fit: contain; }
        .certificate-navigation { margin-bottom: 16px; }
        .print-button { display: block; margin: 0 auto 18px; padding: 9px 18px; border: 0; color: white; cursor: pointer; }
        @media screen and (max-width: 430px) {
            .certificate-navigation { align-items: flex-start; }
            .id-card { margin-top: 16px; margin-bottom: 16px; }
        }
        @media print {
            body { background: #fff; }
            .certificate-navigation, .print-button { display: none; }
            .id-card { margin: 0; border-radius: 0; box-shadow: none; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/certificates.css') }}">
</head>
<body>
<nav class="certificate-navigation" aria-label="Certificate navigation">
    <a href="{{ route('staff.document-requests.index') }}">Back to certificates</a>
    <span>Barangay Anabu I-G / Print preview</span>
</nav>

<button class="print-button" type="button" onclick="window.print()">Print Barangay ID</button>

<main class="id-card" aria-label="Barangay resident identification card">
    <header class="id-header">
        <img src="{{ asset('images/certificates/imus-seal.png') }}" class="seal" alt="City of Imus seal">
        <div class="header-text">
            <div>Republic of the Philippines<br>Province of Cavite<br>City of Imus</div>
            <strong class="office">OFFICE OF THE SANGGUNIANG BARANGAY</strong>
            <strong class="barangay-name">BARANGAY ANABU I-G</strong>
            <strong class="id-title">BARANGAY RESIDENT IDENTIFICATION CARD</strong>
        </div>
        <img src="{{ asset('images/certificates/anabu-source-seal.png') }}" class="seal" alt="Barangay Anabu I-G seal">
        <img src="{{ asset('images/certificates/bagong-pilipinas.png') }}" class="seal" alt="Bagong Pilipinas">
    </header>

    <section class="id-body" aria-label="Resident identity details">
        <img class="photo" src="{{ $certificate->photo_path ? route('staff.issued-certificates.photo', $certificate) : asset('images/official-placeholder.svg') }}" alt="{{ $certificate->photo_path ? 'Resident photo at issuance' : 'Resident photo not provided' }}">
        <div class="details">
            <strong class="name">{{ $certificate->resident_name }}</strong>
            <div class="field"><strong>ID No.:</strong><span>{{ $certificate->certificate_number }}</span></div>
            <div class="field"><strong>Barangay:</strong><span>Anabu I-G, Imus, Cavite</span></div>
            <div class="field"><strong>Date Issued:</strong><span>{{ $certificate->issued_at?->format('M d, Y') ?? '' }}</span></div>
            <div class="field"><strong>Valid Until:</strong><span>{{ $certificate->validUntil()?->format('M d, Y') ?? '' }}</span></div>
        </div>
    </section>

    <footer class="id-footer">
        <div class="verification">
            @if ($certificate->qr_code_path)
                <img class="qr" src="{{ asset($certificate->qr_code_path) }}" alt="Certificate authenticity QR code">
            @endif
            <div>Scan QR code to verify</div>
        </div>
        <img class="imus-footer" src="{{ asset('images/certificates/imus-footer.png') }}" alt="City of Imus footer logo">
    </footer>
</main>
</body>
</html>
