<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Clearance - {{ $certificate->certificate_number }}</title>

    <style>
        * { box-sizing: border-box; }

        @page {
            size: A4;
            margin: 0;
        }

        body {
            margin: 0;
            background: #e5e7eb;
            font-family: "Times New Roman", serif;
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
            font-size: 14px;
            cursor: pointer;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 30px;
            padding: 18mm 20mm;
            background: white;
            position: relative;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            border-bottom: 3px solid #166534;
            padding-bottom: 15px;
        }

        .logo {
            width: 85px;
            height: 85px;
            object-fit: contain;
        }

        .header-text {
            text-align: center;
        }

        .republic {
            font-size: 14px;
        }

        .city {
            font-size: 16px;
            font-weight: bold;
        }

        .barangay {
            font-size: 24px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .office {
            font-size: 15px;
            font-weight: bold;
            margin-top: 4px;
        }

        .title {
            margin: 45px 0 35px;
            text-align: center;
            font-size: 30px;
            font-weight: bold;
            text-decoration: underline;
            text-transform: uppercase;
        }

        .content {
            font-size: 18px;
            line-height: 1.9;
            text-align: justify;
        }

        .resident-name {
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
        }

        .certificate-info {
            margin-top: 35px;
            font-size: 14px;
            line-height: 1.7;
        }

        .footer-area {
            margin-top: 70px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .qr-section {
            text-align: center;
            font-family: Arial, sans-serif;
            font-size: 10px;
        }

        .qr-section img {
            width: 125px;
            height: 125px;
        }

        .signature {
            width: 270px;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #111;
            padding-top: 7px;
            font-weight: bold;
        }

        .verification-code {
            margin-top: 5px;
            font-size: 9px;
        }

        @media print {
            body {
                background: white;
            }

            .print-btn {
                display: none;
            }

            .page {
                margin: 0;
            }
        }
    </style>
<link rel="stylesheet" href="{{ asset('css/certificates.css') }}">
</head>

<body>
<nav class="certificate-navigation" aria-label="Certificate navigation"><a href="{{ route('admin.dashboard', ['screen' => 'certificates']) }}">Back to certificates</a><span>Barangay Anabu I-G / Print preview</span></nav>

<button class="print-btn" onclick="window.print()">
    🖨️ Print Barangay Clearance
</button>

<div class="page">

    <div class="header">

        <img
            src="{{ asset('images/anabu-logo.jpg') }}"
            class="logo"
            alt="Barangay Anabu I-G Logo"
        >

        <div class="header-text">
            <div class="republic">Republic of the Philippines</div>
            <div class="city">CITY OF IMUS</div>
            <div class="barangay">BARANGAY ANABU I-G</div>
            <div class="office">OFFICE OF THE PUNONG BARANGAY</div>
        </div>

    </div>

    <div class="title">
        Barangay Clearance
    </div>

    <div class="content">

        <p><strong>TO WHOM IT MAY CONCERN:</strong></p>

        <p>
            This is to certify that
            <span class="resident-name">
                {{ $certificate->resident_name }}
            </span>
            is a resident of Barangay Anabu I-G, City of Imus,
            Province of Cavite.
        </p>

        <p>
            This further certifies that based on the records available
            in this Barangay, the above-named person is being issued
            this Barangay Clearance upon request for
            <strong>{{ $certificate->purpose ?: 'legal purposes' }}</strong>.
        </p>

        <p>
            Issued this
            <strong>
                {{ \Carbon\Carbon::parse($certificate->issued_at)->format('jS \d\a\y \o\f F Y') }}
            </strong>
            at Barangay Anabu I-G, City of Imus, Cavite.
        </p>

    </div>

    <div class="certificate-info">
        <strong>Certificate No.:</strong>
        {{ $certificate->certificate_number }}
        <br>

        <strong>Amount Paid:</strong>
        {{ $certificate->amount_paid > 0
            ? 'PHP ' . number_format($certificate->amount_paid, 2)
            : 'FREE' }}
    </div>

    <div class="footer-area">

        <div class="qr-section">

            @if($certificate->qr_code_path)
                <img
                    src="{{ asset($certificate->qr_code_path) }}"
                    alt="QR Verification Code"
                >
            @endif

            <div>Scan QR code to verify</div>

            <div class="verification-code">
                {{ $certificate->verification_code }}
            </div>

        </div>

        <div class="signature">
            <div class="signature-line">
                PUNONG BARANGAY
            </div>
        </div>

    </div>

</div>

</body>
</html>