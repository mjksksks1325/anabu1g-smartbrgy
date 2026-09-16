<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <title>{{ $certificate->certificate_number }}</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 30px;
            font-family: "Times New Roman", serif;
            background: #eee;
            color: #111;
        }

        .certificate {
            width: 8.5in;
            min-height: 11in;
            margin: auto;
            padding: 45px 60px;
            background: white;
            border: 3px solid #174c36;
            position: relative;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #174c36;
            padding-bottom: 18px;
        }

        .logo {
            width: 85px;
            height: 85px;
            object-fit: contain;
            margin-bottom: 8px;
        }

        .republic {
            font-size: 14px;
        }

        .barangay {
            font-size: 24px;
            font-weight: bold;
            margin-top: 5px;
        }

        .city {
            font-size: 15px;
            margin-top: 3px;
        }

        .title {
            text-align: center;
            font-size: 30px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 55px 0 40px;
        }

        .body {
            font-size: 18px;
            line-height: 1.8;
            text-align: justify;
        }

        .resident {
            font-weight: bold;
            text-transform: uppercase;
        }

        .details {
            margin-top: 35px;
            font-size: 15px;
            line-height: 1.8;
        }

        .bottom {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 70px;
        }

        .signature {
            width: 280px;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #111;
            padding-top: 6px;
            margin-top: 55px;
        }

        .qr {
            text-align: center;
            font-family: Arial, sans-serif;
            font-size: 10px;
        }

        .qr img {
            width: 130px;
            height: 130px;
        }

        .verify {
            margin-top: 5px;
        }

        .certificate-number {
            font-family: Arial, sans-serif;
            font-size: 11px;
            position: absolute;
            bottom: 18px;
            left: 60px;
        }

        .print-button {
            display: block;
            margin: 20px auto;
            padding: 10px 22px;
            border: 0;
            border-radius: 6px;
            background: #174c36;
            color: white;
            cursor: pointer;
            font-size: 14px;
        }

        @media print {
            body {
                padding: 0;
                background: white;
            }

            .certificate {
                border: none;
                margin: 0;
            }

            .print-button {
                display: none;
            }
        }
    </style>
</head>

<body>

<button class="print-button" onclick="window.print()">
    🖨️ Print Certificate
</button>

<div class="certificate">

    <div class="header">

        <img
            src="{{ asset('images/anabu-logo.jpg') }}"
            class="logo"
            alt="Barangay Logo"
        >

        <div class="republic">
            Republic of the Philippines
        </div>

        <div class="barangay">
            BARANGAY ANABU I-G
        </div>

        <div class="city">
            City of Imus, Province of Cavite
        </div>

    </div>

    <div class="title">
        {{ $certificate->certificate_type }}
    </div>

    <div class="body">

        <p>TO WHOM IT MAY CONCERN:</p>

        <p>
            This is to certify that
            <span class="resident">
                {{ $certificate->resident_name }}
            </span>
            is a resident of Barangay Anabu I-G, City of Imus,
            Province of Cavite.
        </p>

        <p>
            This certification is issued upon the request of the
            above-named person for
            <strong>
                {{ $certificate->purpose ?: 'whatever legal purpose it may serve' }}
            </strong>.
        </p>

        <p>
            Issued this
            <strong>
                {{ \Carbon\Carbon::parse($certificate->issued_at)->format('jS \d\a\y \o\f F Y') }}
            </strong>
            at Barangay Anabu I-G, City of Imus, Cavite.
        </p>

    </div>

    <div class="details">
        Certificate No.:
        <strong>{{ $certificate->certificate_number }}</strong>
        <br>

        Amount Paid:
        <strong>
            {{ $certificate->amount_paid > 0
                ? '₱' . number_format($certificate->amount_paid, 2)
                : 'FREE' }}
        </strong>
    </div>

    <div class="bottom">

        <div class="signature">
            <div class="signature-line">
                Punong Barangay
            </div>
        </div>

        <div class="qr">

            @if($certificate->qr_code_path)
                <img
                    src="{{ asset($certificate->qr_code_path) }}"
                    alt="Certificate QR Code"
                >
            @endif

            <div class="verify">
                Scan to verify authenticity
            </div>

        </div>

    </div>

    <div class="certificate-number">
        Verification Code:
        {{ $certificate->verification_code }}
    </div>

</div>

</body>
</html>