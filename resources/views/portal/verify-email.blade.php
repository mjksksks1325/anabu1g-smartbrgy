@extends('layouts.portal')
@section('title', __('Confirm your email address'))
@section('content')
<x-portal.auth-shell title="Confirm your email address" title-id="verify-email-title">
<section class="card rp-form-card" aria-labelledby="verify-email-title">
    <div class="card-body stack">
        <p class="alert alert-blue" role="status"><span data-portal-i18n="{{ App\Http\Middleware\EnsureResidentEmailVerified::MESSAGE }}">{{ $message }}</span></p>
        <ol class="v2-list">
            <li><span data-portal-i18n="Open the email from Barangay Anabu I-G and select Confirm email address.">{{ __('Open the email from Barangay Anabu I-G and select Confirm email address.') }}</span></li>
            <li><span data-portal-i18n="Check the Spam or Promotions folder if you cannot find it.">{{ __('Check the Spam or Promotions folder if you cannot find it.') }}</span></li>
            <li><span data-portal-i18n="The link works for 60 minutes. Send a new one if it has expired.">{{ __('The link works for 60 minutes. Send a new one if it has expired.') }}</span></li>
        </ol>
        <form method="POST" action="{{ route('portal.verification.send') }}">
            @csrf
            <button class="btn btn-green btn-full" type="submit"><span data-portal-i18n="Send a new confirmation link">{{ __('Send a new confirmation link') }}</span></button>
        </form>
        <p class="form-note"><span data-portal-i18n="Wrong email address? Ask the Barangay Hall to help you update your account.">{{ __('Wrong email address? Ask the Barangay Hall to help you update your account.') }}</span></p>
    </div>
</section>
</x-portal.auth-shell>
@endsection
