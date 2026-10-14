<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ __('Resident registration update') }}</title></head>
<body style="margin:0;padding:0;background:#edf3f7;color:#132238;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border:1px solid #d5e1e9;border-radius:8px;">
    <tr><td style="padding:24px;background:#061a37;color:#fff;border-radius:8px 8px 0 0;">
        <strong style="font-size:20px;">Barangay Anabu I-G</strong><p style="margin:6px 0 0;">{{ __('Resident Portal') }}</p>
    </td></tr>
    <tr><td style="padding:28px 24px;line-height:1.6;font-size:15px;">
        <h1 style="margin:0 0 16px;font-size:24px;color:#061a37;">{{ __('Resident registration update') }}</h1>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f7fb;border:1px solid #cfdfec;border-radius:6px;"><tr><td style="padding:18px;">
            <p style="margin:0;color:#526273;font-size:12px;">{{ __('Application reference') }}</p><strong style="font-size:18px;word-break:break-all;">{{ $reference }}</strong>
            <p style="margin:14px 0 0;"><strong>{{ __($statusLabel) }}</strong></p>
            <p style="margin:6px 0 0;font-size:13px;color:#526273;">{{ __('Reviewed on') }}: {{ $reviewedAt }}</p>
        </td></tr></table>
        <p style="margin:20px 0;">{{ __($instructions) }}</p>
        @if($remarks !== null && $remarks !== '')
            <h2 style="margin:0 0 8px;font-size:16px;">{{ $statusLabel === 'Declined' ? __('Reason for decline') : __('Review remarks') }}</h2>
            <p style="margin:0 0 22px;padding:16px;background:#f2f7fb;border-left:3px solid #0055b8;white-space:pre-line;overflow-wrap:anywhere;">{{ $remarks }}</p>
        @endif
        <table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="background:#0055b8;border-radius:5px;">
            <a href="{{ $actionUrl }}" style="display:inline-block;padding:14px 22px;color:#fff;font-weight:bold;text-decoration:none;">{{ __($actionLabel) }}</a>
        </td></tr></table>
        <p style="margin:20px 0 0;font-size:13px;color:#526273;">{{ __('If the button does not work, open this page:') }}<br><a href="{{ $actionUrl }}" style="color:#0055b8;word-break:break-all;">{{ $actionUrl }}</a></p>
    </td></tr>
    <tr><td style="padding:20px 24px;border-top:1px solid #d5e1e9;color:#526273;font-size:13px;">Barangay Anabu I-G<br>{{ __('City of Imus, Cavite') }}</td></tr>
</table>
</td></tr></table>
</body>
</html>
