@props([
    'title',
    'titleId' => 'page-title',
    'lead' => null,
    'back' => null,
    'backUrl' => null,
])

<div class="rp-auth">
    <aside class="rp-auth-brand" aria-labelledby="{{ $titleId }}-brand">
        <img src="{{ asset('images/anabu-logo.jpg') }}" width="537" height="465" alt="">
        <p class="rp-auth-brand-eyebrow"><span data-portal-i18n="Resident Portal">{{ __('Resident Portal') }}</span></p>
        <h2 id="{{ $titleId }}-brand"><span data-portal-i18n="Barangay Anabu I-G">{{ __('Barangay Anabu I-G') }}</span></h2>
        <ul class="rp-auth-benefits">
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/></svg><span data-portal-i18n="Mag-request ng dokumento online.">{{ __('Mag-request ng dokumento online.') }}</span></li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><span data-portal-i18n="Alamin kung handa na ito.">{{ __('Alamin kung handa na ito.') }}</span></li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 21h18M5 21V10l7-5 7 5v11M9 21v-6h6v6"/></svg><span data-portal-i18n="Kunin at bayaran sa Barangay Hall.">{{ __('Kunin at bayaran sa Barangay Hall.') }}</span></li>
        </ul>
        <p class="rp-auth-privacy"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg><span data-portal-i18n="Pinangangalagaan ang personal na impormasyon alinsunod sa Republic Act 10173 (Data Privacy Act of 2012).">{{ __('Pinangangalagaan ang personal na impormasyon alinsunod sa Republic Act 10173 (Data Privacy Act of 2012).') }}</span></p>
    </aside>
    <div class="rp-auth-main">
        @if($back)
            <a class="v2-back" href="{{ $backUrl }}"><span aria-hidden="true">&#8249;</span> <span data-portal-i18n="{{ $back }}">{{ __($back) }}</span></a>
        @endif
        <h1 id="{{ $titleId }}"><span data-portal-i18n="{{ $title }}">{{ __($title) }}</span></h1>
        @if($lead)
            <p class="rp-auth-lead"><span data-portal-i18n="{{ $lead }}">{{ __($lead) }}</span></p>
        @endif
        {{ $slot }}
        @isset($help)
            <div class="rp-auth-help">{{ $help }}</div>
        @endisset
    </div>
</div>
