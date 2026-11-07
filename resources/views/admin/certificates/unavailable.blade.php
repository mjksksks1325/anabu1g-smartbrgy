{{--
    Shown instead of a certificate that must not be printed: a revoked certificate, or one issued before resident details
    were recorded at issuance (before 4 October 2026), whose printed form cannot be reconstructed without guessing.
    Staff see what the record holds so they can review it; nothing is generated.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $reason === 'revoked' ? 'Certificate revoked' : 'Certificate cannot be reprinted' }}</title>
    <style>
        body { margin: 0; background: #f3f4f6; color: #111827; font: 15px/1.5 system-ui, -apple-system, 'Segoe UI', Arial, sans-serif; }
        main { max-width: 600px; margin: 48px auto; padding: 24px 28px; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; }
        h1 { margin: 0 0 8px; font-size: 20px; }
        dl { display: grid; grid-template-columns: max-content 1fr; gap: 4px 16px; margin: 16px 0; }
        dt { color: #4b5563; }
        dd { margin: 0; }
        a { color: #1d4ed8; }
    </style>
</head>
<body>
<main>
    @if ($reason === 'revoked')
        <h1>This certificate was revoked</h1>
        <p>Revoked certificates are kept on record but are never printed, and QR verification shows them as revoked.</p>
    @else
        <h1>This certificate cannot be reprinted</h1>
        <p>It was issued before the system recorded the resident's details at issuance, and its earlier print layouts changed over time, so its printed form cannot be reconstructed without guessing. Review the record below with the resident. If a current certificate is needed, issue a new one; this record is not changed.</p>
    @endif
    <dl>
        <dt>Certificate number</dt><dd>{{ $certificate->certificate_number }}</dd>
        <dt>Type</dt><dd>{{ $certificate->certificate_type }}</dd>
        <dt>Issued to</dt><dd>{{ $certificate->resident_name }}</dd>
        <dt>Purpose</dt><dd>{{ $certificate->purpose ?: 'Not recorded' }}</dd>
        <dt>Issued</dt><dd>{{ $certificate->issued_at?->format('F d, Y h:i A') ?? 'Not recorded' }}</dd>
        <dt>Issued by</dt><dd>{{ $certificate->issued_by ?: 'Not recorded' }}</dd>
        @if ($reason === 'revoked')
            <dt>Revoked</dt><dd>{{ $certificate->revoked_at?->format('F d, Y h:i A') }} by {{ $certificate->revoker?->name ?? 'a former account' }}</dd>
            <dt>Reason</dt><dd>{{ $certificate->revocation_reason }}</dd>
        @endif
    </dl>
    <p><a href="{{ route('staff.document-requests.index') }}">Back to certificates</a></p>
</main>
</body>
</html>
