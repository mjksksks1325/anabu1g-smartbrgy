<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Barangay Anabu I-G Resident Portal Activation') }}</title>
</head>
<body style="margin:0;padding:0;background-color:#edf3f7;color:#132238;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#edf3f7;">
    <tr><td align="center" style="padding:24px 12px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background-color:#ffffff;border:1px solid #d5e1e9;border-radius:8px;">
            <tr><td style="padding:24px;background-color:#061a37;border-radius:8px 8px 0 0;">
                <table role="presentation" cellpadding="0" cellspacing="0"><tr>
                    <td width="72" style="vertical-align:middle;"><img src="{{ $message->embed(public_path('images/anabu-logo.jpg')) }}" width="56" height="56" alt="Barangay Anabu I-G logo" style="display:block;border:0;border-radius:50%;"></td>
                    <td style="color:#ffffff;vertical-align:middle;">
                        <p style="margin:0;font-size:20px;font-weight:bold;">Barangay Anabu I-G</p>
                        <p style="margin:6px 0 0;font-size:14px;">{{ __('Resident Portal') }}</p>
                        <p style="margin:4px 0 0;font-size:12px;color:#d6e4f1;">{{ __('City of Imus, Cavite') }}</p>
                    </td>
                </tr></table>
            </td></tr>
            <tr><td style="padding:28px 24px;font-size:15px;line-height:1.6;">
                <h1 style="margin:0 0 16px;font-size:24px;line-height:1.3;color:#061a37;">{{ __('Continue your Resident Portal registration') }}</h1>
                <p style="margin:0 0 20px;">{{ __('Your resident record was successfully matched with the Barangay Anabu I-G Resident Records.') }}</p>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f2f7fb;border:1px solid #cfdfec;border-radius:6px;">
                    <tr><td style="padding:18px;">
                        <p style="margin:0;font-size:12px;color:#526273;">{{ __('Resident Number') }}</p>
                        <p style="margin:4px 0 18px;font-size:17px;font-weight:bold;word-break:break-all;">{{ $residentNumber }}</p>
                        <p style="margin:0;font-size:12px;color:#526273;">{{ __('Activation Code') }}</p>
                        <p style="margin:6px 0 0;font-family:Consolas,'Courier New',monospace;font-size:20px;font-weight:bold;line-height:1.5;color:#0055b8;word-break:break-all;overflow-wrap:anywhere;">{{ $activationCode }}</p>
                    </td></tr>
                </table>
                <p style="margin:20px 0 12px;">{{ __('Use this activation code to continue creating your Resident Portal account.') }}</p>
                <p style="margin:0 0 20px;font-weight:bold;">{{ __('This activation code expires in 24 hours.') }}</p>
                <table role="presentation" cellpadding="0" cellspacing="0"><tr><td bgcolor="#0055b8" style="border-radius:5px;text-align:center;">
                    <a href="{{ $registrationUrl }}" style="display:inline-block;padding:14px 22px;color:#ffffff;background-color:#0055b8;border:1px solid #0055b8;border-radius:5px;font-size:15px;font-weight:bold;text-decoration:none;">{{ __('Continue Registration') }}</a>
                </td></tr></table>
                <p style="margin:16px 0 20px;font-size:13px;color:#526273;">{{ __('On the registration page, choose “May activation code na mula sa barangay staff?” and enter your Resident Number and Activation Code. If the activation form is already visible, enter them there.') }}</p>
                <p style="margin:0 0 12px;font-weight:bold;">{{ __('Please do not share your activation code with anyone.') }}</p>
                <p style="margin:0 0 12px;">{{ __('If you did not request this registration, please ignore this email or contact Barangay Anabu I-G.') }}</p>
                <p style="margin:0;font-size:13px;color:#526273;">{{ __('If the button does not work, open this registration page:') }}<br><a href="{{ $registrationUrl }}" style="color:#0055b8;word-break:break-all;">{{ $registrationUrl }}</a></p>
            </td></tr>
            <tr><td style="padding:20px 24px;border-top:1px solid #d5e1e9;font-size:13px;line-height:1.6;color:#526273;">
                <strong style="color:#132238;">Barangay Anabu I-G</strong><br>{{ __('City of Imus, Cavite') }}</td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
