<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Verification - SmartBrgy</title>

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #07111f;
            color: #e5eef7;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .verify-card {
            width: 100%;
            max-width: 560px;
            background: #0d1b2a;
            border: 1px solid #1f3b52;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 20px 50px rgba(0,0,0,.35);
        }

        .status {
            text-align: center;
            margin-bottom: 24px;
        }

        .status-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .status-title {
            color: #22c55e;
            font-size: 22px;
            font-weight: 700;
        }

        .status-sub {
            color: #94a3b8;
            margin-top: 6px;
            font-size: 14px;
        }

        .detail {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 12px 0;
            border-bottom: 1px solid #1f2f40;
        }

        .detail:last-child {
            border-bottom: none;
        }

        .label {
            color: #94a3b8;
            font-size: 13px;
        }

        .value {
            text-align: right;
            font-weight: 600;
            font-size: 14px;
        }

        .footer {
            text-align: center;
            color: #64748b;
            font-size: 12px;
            margin-top: 24px;
        }
    </style>
</head>

<body>

<div class="verify-card">

    <div class="status">
        <div class="status-icon">✅</div>

        <div class="status-title">
            Authentic Barangay Document
        </div>

        <div class="status-sub">
            This certificate was issued by Barangay Anabu I-G.
        </div>
    </div>

    <div class="detail">
        <div class="label">Certificate Number</div>
        <div class="value">{{ $certificate->certificate_number }}</div>
    </div>

    <div class="detail">
        <div class="label">Document Type</div>
        <div class="value">{{ $certificate->certificate_type }}</div>
    </div>

    <div class="detail">
        <div class="label">Resident Name</div>
        <div class="value">{{ $certificate->resident_name }}</div>
    </div>

    <div class="detail">
        <div class="label">Purpose</div>
        <div class="value">
            {{ $certificate->purpose ?: 'Not specified' }}
        </div>
    </div>

    <div class="detail">
        <div class="label">Issued Date</div>
        <div class="value">
            {{ optional($certificate->issued_at)->format('F d, Y h:i A') }}
        </div>
    </div>

    <div class="detail">
        <div class="label">Issued By</div>
        <div class="value">
            {{ $certificate->issued_by ?: 'Barangay Staff' }}
        </div>
    </div>

    <div class="detail">
        <div class="label">Verification Code</div>
        <div class="value">
            {{ $certificate->verification_code }}
        </div>
    </div>

    <div class="footer">
        SmartBrgy — Barangay Anabu I-G, Imus City
    </div>

</div>

</body>
</html>