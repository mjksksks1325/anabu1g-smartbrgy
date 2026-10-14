@props(['target', 'resident' => false])
<button {{ $attributes->class(['password-visibility-toggle', 'resident-password-toggle' => $resident]) }} type="button" data-password-toggle="{{ $target }}" aria-controls="{{ $target }}" aria-pressed="false" aria-label="{{ __('Show password') }}" title="{{ __('Show password') }}" @if($resident) data-portal-i18n-aria-label="Show password" data-portal-i18n-title="Show password" @endif>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="password-eye-slash" d="m3 3 18 18"/></svg>
</button>
