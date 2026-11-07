{{--
    Public QR verification. It shows only what a verifier needs to match the paper: the certificate number, document
    type, the holder's initials, the issue date and whether the certificate is still valid. The resident's full name,
    purpose and the issuing staff member stay in the staff records and audit log.
--}}
@extends('layouts.portal')
@section('title', __('Certificate verification'))
@section('band')
<x-portal.page-band title="Certificate verification" title-id="verify-title">
    <p><span data-portal-i18n="Resulta ng pag-scan ng QR code sa dokumentong inisyu ng Barangay Anabu I-G.">{{ __('Resulta ng pag-scan ng QR code sa dokumentong inisyu ng Barangay Anabu I-G.') }}</span></p>
</x-portal.page-band>
@endsection
@section('content')
<div class="v2-verify">
    @if ($state === 'revoked')
        <section class="v2-verify-result" data-result="revoked" aria-labelledby="verify-result-title">
            <span class="v2-verify-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M8.5 8.5l7 7M15.5 8.5l-7 7"/></svg></span>
            <div>
                <h2 id="verify-result-title"><span data-portal-i18n="Revoked Barangay Document">{{ __('Revoked Barangay Document') }}</span></h2>
                <p><span data-portal-i18n="Barangay Anabu I-G revoked this document. It is no longer valid. Contact the Barangay Hall if you need a current copy.">{{ __('Barangay Anabu I-G revoked this document. It is no longer valid. Contact the Barangay Hall if you need a current copy.') }}</span></p>
            </div>
        </section>
        <dl class="v2-card v2-details">
            <div><dt><span data-portal-i18n="Certificate Number">{{ __('Certificate Number') }}</span></dt><dd class="v2-ref">{{ $certificate->certificate_number }}</dd></div>
            <div><dt><span data-portal-i18n="Document Type">{{ __('Document Type') }}</span></dt><dd><span data-portal-i18n="{{ $certificate->certificate_type }}">{{ __($certificate->certificate_type) }}</span></dd></div>
            <div><dt><span data-portal-i18n="Revoked on">{{ __('Revoked on') }}</span></dt><dd>{{ $certificate->revoked_at?->format('F d, Y') }}</dd></div>
        </dl>
    @elseif ($state === 'expired')
        <section class="v2-verify-result" data-result="expired" aria-labelledby="verify-result-title">
            <span class="v2-verify-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
            <div>
                <h2 id="verify-result-title"><span data-portal-i18n="Expired Barangay Document">{{ __('Expired Barangay Document') }}</span></h2>
                <p><span data-portal-i18n="This document is no longer valid. Contact Barangay Anabu I-G if you need a current copy.">{{ __('This document is no longer valid. Contact Barangay Anabu I-G if you need a current copy.') }}</span></p>
            </div>
        </section>
    @else
        <section class="v2-verify-result" data-result="authentic" aria-labelledby="verify-result-title">
            <span class="v2-verify-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></svg></span>
            <div>
                <h2 id="verify-result-title"><span data-portal-i18n="Authentic Barangay Document">{{ __('Authentic Barangay Document') }}</span></h2>
                <p><span data-portal-i18n="This certificate was issued by Barangay Anabu I-G.">{{ __('This certificate was issued by Barangay Anabu I-G.') }}</span></p>
            </div>
        </section>

        <section aria-labelledby="verify-details-title">
            <h2 id="verify-details-title" class="v2-section-title"><span data-portal-i18n="Detalye ng dokumento">{{ __('Detalye ng dokumento') }}</span></h2>
            <dl class="v2-card v2-details">
                <div><dt><span data-portal-i18n="Certificate Number">{{ __('Certificate Number') }}</span></dt><dd class="v2-ref">{{ $certificate->certificate_number }}</dd></div>
                <div><dt><span data-portal-i18n="Document Type">{{ __('Document Type') }}</span></dt><dd><span data-portal-i18n="{{ $certificate->certificate_type }}">{{ __($certificate->certificate_type) }}</span></dd></div>
                <div><dt><span data-portal-i18n="Holder's initials">{{ __("Holder's initials") }}</span></dt><dd class="v2-ref">{{ $certificate->holderInitials() }}</dd></div>
                <div><dt><span data-portal-i18n="Issued Date">{{ __('Issued Date') }}</span></dt><dd>{{ $certificate->issued_at?->format('F d, Y') }}</dd></div>
                <div><dt><span data-portal-i18n="Status">{{ __('Status') }}</span></dt><dd><span data-portal-i18n="Valid">{{ __('Valid') }}</span></dd></div>
                <div><dt><span data-portal-i18n="Valid Until">{{ __('Valid Until') }}</span></dt><dd>{{ $certificate->validUntil()?->format('F d, Y') }}</dd></div>
            </dl>
        </section>
    @endif

    <aside class="v2-callout" aria-labelledby="verify-help-title">
        <svg class="v2-callout-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5h.01"/></svg>
        <div>
            <h2 id="verify-help-title"><span data-portal-i18n="Paano gamitin ang resultang ito">{{ __('Paano gamitin ang resultang ito') }}</span></h2>
            <p><span data-portal-i18n="Compare the certificate number, document type and the initials of the holder's name with the paper you have. If they differ, or if in doubt, contact the Barangay Anabu I-G Barangay Hall.">{{ __("Compare the certificate number, document type and the initials of the holder's name with the paper you have. If they differ, or if in doubt, contact the Barangay Anabu I-G Barangay Hall.") }}</span></p>
        </div>
    </aside>
</div>
@endsection
