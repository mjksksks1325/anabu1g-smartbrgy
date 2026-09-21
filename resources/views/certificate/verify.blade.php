<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Verification - SmartBrgy</title>

    <link rel="icon" type="image/jpeg" href="{{ asset('images/anabu-logo.jpg') }}">
    <link rel="stylesheet" href="{{ asset('css/government.css') }}">
</head>

<body class="civic-page">
@include('partials.civic-header')

<main id="main-content" tabindex="-1" class="civic-verification civic-surface">

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

</main>
@include('partials.civic-footer')
</body>
</html>