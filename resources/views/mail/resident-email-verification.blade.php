<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ __('Confirm your email address') }}</title></head>
<body style="margin:0;padding:0;background:#edf3f7;color:#132238;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border:1px solid #d5e1e9;border-radius:8px;">
    <tr><td style="padding:24px;background:#061a37;color:#fff;border-radius:8px 8px 0 0;">
        <strong style="font-size:20px;">Barangay Anabu I-G</strong><p style="margin:6px 0 0;">{{ __('Resident Portal') }}</p>
    </td></tr>
    <tr><td style="padding:28px 24px;line-height:1.6;font-size:15px;">
        <h1 style="margin:0 0 16px;font-size:24px;color:#061a37;">{{ __('Confirm your email address') }}</h1>
        <p style="margin:0 0 20px;">{{ __('Your resident account was created. Open the link below to confirm this email address. You can request documents after it is confirmed.') }}</p>
        <table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="background:#0055b8;border-radius:5px;">
            <a href="{{ $verificationUrl }}" style="display:inline-block;padding:14px 22px;color:#fff;font-weight:bold;text-decoration:none;">{{ __('Confirm email address') }}</a>
        </td></tr></table>
        <p style="margin:20px 0 0;font-size:13px;color:#526273;">{{ __('The link works for :minutes minutes. If it expires, sign in and send a new one.', ['minutes' => $minutes]) }}</p>
        <p style="margin:12px 0 0;font-size:13px;color:#526273;">{{ __('If you did not create this account, ignore this email and tell the Barangay Hall.') }}</p>
        <p style="margin:12px 0 0;font-size:13px;color:#526273;">{{ __('If the button does not work, open this page:') }}<br><a href="{{ $verificationUrl }}" style="color:#0055b8;word-break:break-all;">{{ $verificationUrl }}</a></p>
    </td></tr>
    <tr><td style="padding:20px 24px;border-top:1px solid #d5e1e9;color:#526273;font-size:13px;">Barangay Anabu I-G<br>{{ __('City of Imus, Cavite') }}</td></tr>
</table>
</td></tr></table>
</body>
</html>
