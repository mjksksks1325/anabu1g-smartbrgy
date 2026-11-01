@php
    $rowHint = match (true) {
        $item->status === 'ready_for_release' => 'Puwede nang kunin sa Barangay Hall.',
        $item->status === 'rejected' => 'Tingnan ang dahilan sa detalye ng request.',
        filled($item->remarks) => 'May paalala mula sa barangay.',
        default => null,
    };
@endphp
<li @class(['request-item', 'is-ready' => $item->status === 'ready_for_release', 'is-rejected' => $item->status === 'rejected']) data-status="{{ $item->status }}" data-request-id="{{ $item->id }}" data-request-version="{{ $item->historyVersion() }}">
    <div class="request-row-document">
        <h2><a href="{{ route('portal.account.requests.show', $item->reference_code) }}"><span data-portal-i18n="{{ $item->document_type }}">{{ __($item->document_type) }}</span></a></h2>
        <p class="request-row-reference"><span class="sr-only"><span data-portal-i18n="Reference no.">{{ __('Reference no.') }}</span> </span><span class="reference">{{ $item->reference_code }}</span></p>
        @if($rowHint)<p class="request-row-hint"><span data-portal-i18n="{{ $rowHint }}">{{ __($rowHint) }}</span></p>@endif
    </div>
    <p class="request-row-date"><span class="request-row-label"><span data-portal-i18n="Na-submit">{{ __('Na-submit') }}</span> </span><time datetime="{{ $item->created_at->toDateString() }}">{{ $item->created_at->format('M j, Y') }}</time></p>
    <div class="request-row-status">@include('portal.partials.request-status-pill', ['status' => $item->status])</div>
    <a class="request-row-link" href="{{ route('portal.account.requests.show', $item->reference_code) }}" aria-hidden="true" tabindex="-1"><span data-portal-i18n="Tingnan">{{ __('Tingnan') }}</span> <span aria-hidden="true">&#8250;</span></a>
</li>
