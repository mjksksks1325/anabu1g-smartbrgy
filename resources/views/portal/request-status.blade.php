@extends('layouts.portal')
@section('title', __($item->document_type))
@php
    $stages = ['pending', 'processing', 'approved', 'ready_for_release', 'released'];
    $currentStage = array_search($item->status, $stages, true);
    $statusNote = config('portal.request_statuses.'.$item->status.'.note');
@endphp
@section('band')
<x-portal.page-band :title="$item->document_type" parent="My requests" :parent-url="route('portal.account')">
    <p class="v2-ref-row" data-resident-private>
        <span class="v2-ref" id="tracker-reference"><span class="sr-only"><span data-portal-i18n="Reference no.">{{ __('Reference no.') }}</span> </span>{{ $item->reference_code }}</span>
        <button type="button" class="btn btn-outline btn-small" data-copy-reference="{{ $item->reference_code }}" hidden><span data-portal-i18n="Kopyahin ang code">{{ __('Kopyahin ang code') }}</span></button>
    </p>
    <p class="v2-submitted"><span data-portal-i18n="Na-submit">{{ __('Na-submit') }}</span>: <time datetime="{{ $item->created_at->toDateString() }}">{{ $item->created_at->format('M j, Y') }}</time></p>
</x-portal.page-band>
@endsection
@section('content')
<div class="v2-tracker" data-resident-private>
    <div class="v2-tracker-main">
        <section class="v2-card v2-tracker-state" data-status="{{ $item->status }}" aria-labelledby="tracker-status-title">
            <h2 id="tracker-status-title" class="v2-label"><span data-portal-i18n="Status ngayon">{{ __('Status ngayon') }}</span></h2>
            @include('portal.partials.request-status-pill', ['status' => $item->status])
            @if($statusNote)<p><span data-portal-i18n="{{ $statusNote }}">{{ __($statusNote) }}</span></p>@endif
        </section>

        @if($item->status === 'ready_for_release')
            <section class="v2-card v2-collect" aria-labelledby="tracker-collect-title">
                <h2 id="tracker-collect-title"><span data-portal-i18n="Paano kunin">{{ __('Paano kunin') }}</span></h2>
                <ol class="v2-collect-steps">
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
                        <p><span data-portal-i18n="Pumunta sa Barangay Hall ng Anabu I-G.">{{ __('Pumunta sa Barangay Hall ng Anabu I-G.') }}</span></p>
                        <p class="v2-summary-note"><span data-portal-i18n="Office hours:">{{ __('Office hours:') }}</span> <span class="tag-unconfirmed"><span data-portal-i18n="Hindi pa kumpirmado">{{ __('Hindi pa kumpirmado') }}</span></span></p>
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M6 16a3 3 0 0 1 6 0M14 10h4M14 14h4"/></svg>
                        <p><span data-portal-i18n="Dalhin ang valid ID at ang reference number na">{{ __('Dalhin ang valid ID at ang reference number na') }}</span> <span class="v2-ref">{{ $item->reference_code }}</span>.</p>
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/></svg>
                        <p><span data-portal-i18n="Bayaran ang fee sa Barangay Hall, kung mayroon.">{{ __('Bayaran ang fee sa Barangay Hall, kung mayroon.') }}</span></p>
                    </li>
                </ol>
            </section>
        @endif

        @if($item->status === 'rejected')
            <section class="v2-card v2-card-rejected" aria-labelledby="tracker-reason-title">
                <h2 id="tracker-reason-title"><span data-portal-i18n="Dahilan">{{ __('Dahilan') }}</span></h2>
                <p>@if($item->rejection_reason){{ $item->rejection_reason }}@else<span data-portal-i18n="Walang nakasulat na dahilan. Magtanong sa Barangay Hall.">{{ __('Walang nakasulat na dahilan. Magtanong sa Barangay Hall.') }}</span>@endif</p>
                <p><span data-portal-i18n="Puwedeng mag-submit ng bagong request kapag naayos na ang problema.">{{ __('Puwedeng mag-submit ng bagong request kapag naayos na ang problema.') }}</span></p>
                <a class="btn btn-green" href="{{ route('portal.request.create') }}"><span data-portal-i18n="Request a document">{{ __('Request a document') }}</span></a>
            </section>
        @endif

        @if($item->remarks)
            <section class="v2-card v2-card-remarks" aria-labelledby="tracker-remarks-title">
                <h2 id="tracker-remarks-title"><span data-portal-i18n="Paalala mula sa barangay">{{ __('Paalala mula sa barangay') }}</span></h2>
                <p>{{ $item->remarks }}</p>
            </section>
        @endif

        <section aria-labelledby="tracker-details-title">
            <h2 id="tracker-details-title" class="v2-section-title"><span data-portal-i18n="Detalye ng request">{{ __('Detalye ng request') }}</span></h2>
            <dl class="v2-card v2-details">
                <div><dt><span data-portal-i18n="Purpose">{{ __('Purpose') }}</span></dt><dd>{{ $item->purpose ?: '—' }}</dd></div>
                @if($item->business_name)<div><dt><span data-portal-i18n="Business name">{{ __('Business name') }}</span></dt><dd>{{ $item->business_name }}</dd></div>@endif
                @if($certificateType)
                    <div><dt><span data-portal-i18n="Fee">{{ __('Fee') }}</span></dt><dd><span data-portal-i18n="{{ $certificateType->feeLabel() }}">{{ __($certificateType->feeLabel()) }}</span>@if($certificateType->fee() > 0) · <span data-portal-i18n="babayaran sa Barangay Hall">{{ __('babayaran sa Barangay Hall') }}</span>@endif @if($certificateType === App\CertificateType::BusinessClearance)<span class="tag-unconfirmed"><span data-portal-i18n="To be confirmed">{{ __('To be confirmed') }}</span></span>@endif</dd></div>
                @endif
            </dl>
        </section>

        <p><a class="btn btn-outline v2-btn-icon" href="{{ route('portal.information') }}#help"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 0 1 5 0c0 2-2.5 2-2.5 4M12 17h.01"/></svg><span data-portal-i18n="Humingi ng tulong">{{ __('Humingi ng tulong') }}</span></a></p>
    </div>

    <aside class="v2-tracker-aside">
        <section class="v2-card" aria-labelledby="tracker-stages-title">
            <h2 id="tracker-stages-title"><span data-portal-i18n="Mga yugto ng request">{{ __('Mga yugto ng request') }}</span></h2>
            <ol class="v2-stages">
                @if($item->status === 'rejected')
                    <li class="is-done" data-stage="pending"><span><span data-portal-i18n="Received">{{ __('Received') }}</span></span></li>
                    <li class="is-current is-stopped" data-stage="rejected" aria-current="step"><span><span data-portal-i18n="Not approved">{{ __('Not approved') }}</span></span></li>
                @else
                    @foreach($stages as $index => $stage)
                        @php($stageLabel = config('portal.request_statuses.'.$stage.'.label'))
                        <li @class(['is-done' => $currentStage !== false && $index < $currentStage, 'is-current' => $index === $currentStage]) data-stage="{{ $stage }}" @if($index === $currentStage) aria-current="step" @endif>
                            <span>
                                <span class="v2-stage-name"><span data-portal-i18n="{{ $stageLabel }}">{{ __($stageLabel) }}</span></span>
                                @if($index === $currentStage)
                                    <span class="v2-chip"><span data-portal-i18n="Nandito na ngayon">{{ __('Nandito na ngayon') }}</span></span>
                                    @if($statusNote)<span class="v2-stage-note"><span data-portal-i18n="{{ $statusNote }}">{{ __($statusNote) }}</span></span>@endif
                                @endif
                            </span>
                        </li>
                    @endforeach
                @endif
            </ol>
            <p class="v2-summary-note"><span data-portal-i18n="Petsa lang ng pag-submit ang naitatala ng system, kaya walang petsa ang bawat yugto.">{{ __('Petsa lang ng pag-submit ang naitatala ng system, kaya walang petsa ang bawat yugto.') }}</span></p>
        </section>
    </aside>
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/reference-copy.js') }}?v={{ filemtime(public_path('js/reference-copy.js')) }}"></script>
@endpush
