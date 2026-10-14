Barangay Anabu I-G / {{ __('Resident Portal') }}
{{ __('Resident registration update') }}

{{ __('Application reference') }}: {{ $reference }}
{{ __($statusLabel) }}
{{ __('Reviewed on') }}: {{ $reviewedAt }}

{{ __($instructions) }}
@if($remarks !== null && $remarks !== '')
{{ $statusLabel === 'Declined' ? __('Reason for decline') : __('Review remarks') }}:
{{ $remarks }}
@endif

{{ __($actionLabel) }}: {{ $actionUrl }}
