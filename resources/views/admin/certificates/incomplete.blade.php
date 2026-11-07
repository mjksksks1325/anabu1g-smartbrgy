{{--
    Shown instead of a certificate when its record lacks a detail the printed template states (an issued certificate),
    or when a request cannot be issued yet (a preview: missing details or eligibility problems).
--}}
@php($isPreview = $isPreview ?? false)
@php($problems = $problems ?? [])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $isPreview ? 'Certificate cannot be issued yet' : 'Certificate cannot be printed' }}</title>
    <style>
        body { margin: 0; background: #f3f4f6; color: #111827; font: 15px/1.5 system-ui, -apple-system, 'Segoe UI', Arial, sans-serif; }
        main { max-width: 560px; margin: 48px auto; padding: 24px 28px; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; }
        h1 { margin: 0 0 8px; font-size: 20px; }
        ul { padding-left: 20px; }
        a { color: #1d4ed8; }
    </style>
</head>
<body>
<main>
    @if ($isPreview)
        <h1>This certificate cannot be issued yet</h1>
        <p>The {{ $certificate->certificate_type }} for this request would not be complete. Resolve the following, then preview it again:</p>
    @else
        <h1>This certificate cannot be printed</h1>
        <p>{{ $certificate->certificate_type }} {{ $certificate->certificate_number }} does not record every detail its printed form states, and a certificate is never printed with blank details. Missing:</p>
    @endif
    <ul>
        @foreach ($problems as $problem)
            <li>{{ $problem }}</li>
        @endforeach
        @foreach ($missingDetails as $detail)
            <li>{{ $isPreview ? 'Missing: '.$detail : $detail }}</li>
        @endforeach
    </ul>
    @if ($isPreview)
        <p>Details from the resident record are completed in Resident Records. Residency details are entered in the Print &amp; Release dialog.</p>
    @else
        <p>Issued certificates cannot be edited. Complete the resident record in Resident Records, then issue a new certificate.</p>
    @endif
    <p><a href="{{ route('staff.document-requests.index') }}">Back to certificates</a></p>
</main>
</body>
</html>
