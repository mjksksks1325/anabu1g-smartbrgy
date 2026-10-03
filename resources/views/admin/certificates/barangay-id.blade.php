<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay ID</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #e5e7eb;
            font-family: 'Times New Roman', serif;
            color: #111;
        }

        .print-btn {
            display: block;
            margin: 20px auto;
            padding: 10px 24px;
            border: 0;
            border-radius: 6px;
            background: #166534;
            color: white;
            cursor: pointer;
        }

        .id-card {
            width: 85.6mm;
            height: 54mm;
            margin: 30px auto;
            background: white;
            border: 1px solid #111;
            border-radius: 0;
            overflow: hidden;
            position: relative;
        }

        .id-header {
            background: white;
            color: #111;
            border-bottom: 1px solid #111;
            padding: 2px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .logo {
            width: 28px;
            height: 28px;
            object-fit: contain;
            background: white;
            border-radius: 50%;
        }

        .header-text {
            flex: 1;
            text-align: center;
            font-size: 6pt;
            line-height: 6pt;
        }

        .barangay-name {
            font-size: 6pt;
            font-weight: bold;
        }

        .location {
            font-size: 6pt;
        }

        .id-title {
            font-size: 6pt;
            font-weight: bold;
            margin-top: 2px;
        }

        .id-body {
            display: flex;
            padding: 4px;
            gap: 10px;
        }

        .photo-placeholder {
            width: 18mm;
            height: 20mm;
            object-fit: contain;
            border: 1px solid #aaa;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            color: #666;
        }

        .details {
            flex: 1;
            font-size: 9px;
        }

        .name {
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .field {
            margin-bottom: 4px;
        }

        .qr {
            position: absolute;
            left: 6px;
            bottom: 5px;
            text-align: center;
            font-size: 6px;
        }

        .qr img {
            width: 43px;
            height: 43px;
        }

        .imus-footer {
            position: absolute;
            right: 6px;
            bottom: 5px;
            width: 12mm;
            height: 15mm;
            object-fit: contain;
        }

        @media print {
            body {
                background: white;
            }

            .print-btn {
                display: none;
            }

            .id-card {
                margin: 0;
            }
        }
    </style>
<link rel="stylesheet" href="{{ asset('css/certificates.css') }}">
</head>

<body>
<nav class="certificate-navigation" aria-label="Certificate navigation"><a href="{{ route('admin.document-requests.index') }}">Back to certificates</a><span>Barangay Anabu I-G / Print preview</span></nav>

<button class="print-btn" onclick="window.print()">
     Print Barangay ID
</button>

<div class="id-card">

    <div class="id-header">

        <img
            src="{{ asset('images/certificates/imus-seal.png') }}"
            class="logo"
            alt="City of Imus seal"
        >
        <img src="{{ asset('images/certificates/anabu-source-seal.png') }}" class="logo" alt="Barangay Anabu I-G seal">

        <div class="header-text">
            <div>Republic of the Philippines<br>Province of Cavite<br>City of Imus</div>
            <div>OFFICE OF THE SANGGUNIANG BARANGAY</div>
            <div class="barangay-name">
                BARANGAY ANABU I-G
            </div>

            <div class="id-title">
                BARANGAY RESIDENT IDENTIFICATION CARD
            </div>
        </div>
        <img src="{{ asset('images/certificates/bagong-pilipinas.png') }}" class="logo" alt="Bagong Pilipinas">

    </div>

    <div class="id-body">

        <img class="photo-placeholder" src="{{ $certificate->photo_path ? route('admin.issued-certificates.photo', $certificate) : asset('images/official-placeholder.svg') }}" alt="{{ $certificate->photo_path ? 'Resident photo at issuance' : 'Resident photo not provided' }}">

        <div class="details">

            <div class="name">
                {{ $certificate->resident_name }}
            </div>

            <div class="field">
                <strong>ID No.:</strong>
                {{ $certificate->certificate_number }}
            </div>

            <div class="field">
                <strong>Barangay:</strong>
                Anabu I-G
            </div>

            <div class="field">
                <strong>City:</strong>
                Imus, Cavite
            </div>

            <div class="field">
                <strong>Date Issued:</strong>
                {{ \Carbon\Carbon::parse($certificate->issued_at)->format('M d, Y') }}
            </div>

        </div>

    </div>

    <img class="imus-footer" src="{{ asset('images/certificates/imus-footer.png') }}" alt="City of Imus footer logo">

    <div class="qr">
        @if($certificate->qr_code_path)
            <img src="{{ asset($certificate->qr_code_path) }}" alt="Certificate authenticity QR code">
        @endif

        <div>VERIFY</div>
    </div>

</div>

</body>
</html>
