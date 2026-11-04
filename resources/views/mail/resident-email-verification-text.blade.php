Barangay Anabu I-G / {{ __('Resident Portal') }}
{{ __('Confirm your email address') }}

{{ __('Your resident account was created. Open the link below to confirm this email address. You can request documents after it is confirmed.') }}

{{ __('Confirm email address') }}: {{ $verificationUrl }}

{{ __('The link works for :minutes minutes. If it expires, sign in and send a new one.', ['minutes' => $minutes]) }}
{{ __('If you did not create this account, ignore this email and tell the Barangay Hall.') }}
