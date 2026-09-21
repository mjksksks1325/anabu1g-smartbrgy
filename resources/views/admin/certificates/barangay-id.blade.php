<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay ID - {{ $certificate->certificate_number }}</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #e5e7eb;
            font-family: Arial, sans-serif;
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
            border: 2px solid #166534;
            border-radius: 8px;
            overflow: hidden;
            position: relative;
        }

        .id-header {
            background: #166534;
            color: white;
            padding: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logo {
            width: 35px;
            height: 35px;
            object-fit: contain;
            background: white;
            border-radius: 50%;
        }

        .header-text {
            line-height: 1.15;
        }

        .barangay-name {
            font-size: 13px;
            font-weight: bold;
        }

        .location {
            font-size: 9px;
        }

        .id-title {
            font-size: 10px;
            font-weight: bold;
            margin-top: 2px;
        }

        .id-body {
            display: flex;
            padding: 8px;
            gap: 10px;
        }

        .photo-placeholder {
            width: 26mm;
            height: 30mm;
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
            right: 6px;
            bottom: 5px;
            text-align: center;
            font-size: 6px;
        }

        .qr img {
            width: 43px;
            height: 43px;
        }

        .certificate-number {
            position: absolute;
            left: 8px;
            bottom: 5px;
            font-size: 6px;
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
<nav class="certificate-navigation" aria-label="Certificate navigation"><a href="{{ route('admin.dashboard', ['screen' => 'certificates']) }}">Back to certificates</a><span>Barangay Anabu I-G / Print preview</span></nav>

<button class="print-btn" onclick="window.print()">
    🖨️ Print Barangay ID
</button>

<div class="id-card">

    <div class="id-header">

        <img
            src="{{ asset('images/anabu-logo.jpg') }}"
            class="logo"
        >

        <div class="header-text">
            <div class="barangay-name">
                BARANGAY ANABU I-G
            </div>

            <div class="location">
                City of Imus, Cavite
            </div>

            <div class="id-title">
                BARANGAY RESIDENT IDENTIFICATION CARD
            </div>
        </div>

    </div>

    <div class="id-body">

        <div class="photo-placeholder">
            RESIDENT PHOTO
        </div>

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

    <div class="certificate-number">
        Verification: {{ $certificate->verification_code }}
    </div>

    <div class="qr">
        @if($certificate->qr_code_path)
            <img src="{{ asset($certificate->qr_code_path) }}">
        @endif

        <div>VERIFY</div>
    </div>

</div>

</body>
</html>