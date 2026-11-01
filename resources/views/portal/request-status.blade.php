@extends('layouts.portal')
@section('title', __($item->document_type))
@php
    $stages = ['pending', 'processing', 'approved', 'ready_for_release', 'released'];
    $currentStage = array_search($item->status, $stages, true);
@endphp
@section('band')
<x-portal.page-band :title="$item->document_type" parent="My requests" :parent-url="route('portal.account')">
    <p class="v2-ref-row" data-resident-private>
        <span><span data-portal-i18n="Reference no.">{{ __('Reference no.') }}</span> <span class="v2-ref" id="tracker-reference">{{ $item->reference_code }}</span></span>
        <button type="button" class="btn btn-outline btn-small" data-copy-reference="{{ $item->reference_code }}" hidden><span data-portal-i18n="Kopyahin ang code">{{ __('Kopyahin ang code') }}</span></button>
        <span><span data-portal-i18n="Na-submit">{{ __('Na-submit') }}</span> <time datetime="{{ $item->created_at->toDateString() }}">{{ $item->created_at->format('M j, Y') }}</time></span>
    </p>
</x-portal.page-band>
@endsection
@section('content')
<div class="v2-tracker" data-resident-private>
    <div class="v2-tracker-main">
        <section class="v2-tracker-state" data-status="{{ $item->status }}" aria-labelledby="tracker-status-title">
            <h2 id="tracker-status-title" class="sr-only"><span data-portal-i18n="Status ng request">{{ __('Status ng request') }}</span></h2>
            @include('portal.partials.request-status-pill', ['status' => $item->status])
            @if($note = config('portal.request_statuses.'.$item->status.'.note'))<p><span data-portal-i18n="{{ $note }}">{{ __($note) }}</span></p>@endif
        </section>

        @if($item->status === 'ready_for_release')
            <section class="v2-card" aria-labelledby="tracker-collect-title">
                <h2 id="tracker-collect-title"><span data-portal-i18n="Paano kunin">{{ __('Paano kunin') }}</span></h2>
                <ol class="v2-list">
                    <li><span data-portal-i18n="Pumunta sa Barangay Hall ng Anabu I-G.">{{ __('Pumunta sa Barangay Hall ng Anabu I-G.') }}</span> <span class="tag-unconfirmed"><span data-portal-i18n="Hindi pa kumpirmado">{{ __('Hindi pa kumpirmado') }}</span></span> <span data-portal-i18n="ang office hours.">{{ __('ang office hours.') }}</span></li>
                    <li><span data-portal-i18n="Dalhin ang valid ID at ang reference number na">{{ __('Dalhin ang valid ID at ang reference number na') }}</span> <span class="v2-ref">{{ $item->reference_code }}</span>.</li>
                    <li><span data-portal-i18n="Bayaran ang fee sa Barangay Hall, kung mayroon.">{{ __('Bayaran ang fee sa Barangay Hall, kung mayroon.') }}</span></li>
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

        <section class="v2-card" aria-labelledby="tracker-help-title">
            <h2 id="tracker-help-title"><span data-portal-i18n="May tanong tungkol sa request na ito?">{{ __('May tanong tungkol sa request na ito?') }}</span></h2>
            <p><span data-portal-i18n="Pumunta sa Barangay Hall at dalhin ang reference number.">{{ __('Pumunta sa Barangay Hall at dalhin ang reference number.') }}</span> <a href="{{ route('portal.information') }}#office"><span data-portal-i18n="Address at detalye ng Barangay Hall">{{ __('Address at detalye ng Barangay Hall') }}</span></a></p>
        </section>
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
                                <span data-portal-i18n="{{ $stageLabel }}">{{ __($stageLabel) }}</span>
                                @if($index === $currentStage)<span class="v2-stage-now"><span data-portal-i18n="Nandito na ngayon">{{ __('Nandito na ngayon') }}</span></span>@endif
                            </span>
                        </li>
                    @endforeach
                @endif
            </ol>
            <p class="v2-summary-note"><span data-portal-i18n="Petsa lang ng pag-submit ang naitatala ng system, kaya walang petsa ang bawat yugto.">{{ __('Petsa lang ng pag-submit ang naitatala ng system, kaya walang petsa ang bawat yugto.') }}</span></p>
        </section>

        <section class="v2-card" aria-labelledby="tracker-details-title">
            <h2 id="tracker-details-title"><span data-portal-i18n="Detalye">{{ __('Detalye') }}</span></h2>
            <dl class="v2-summary">
                <div><dt><span data-portal-i18n="Purpose">{{ __('Purpose') }}</span></dt><dd>{{ $item->purpose ?: '—' }}</dd></div>
                @if($item->business_name)<div><dt><span data-portal-i18n="Business name">{{ __('Business name') }}</span></dt><dd>{{ $item->business_name }}</dd></div>@endif
                @if($certificateType)
                    <div><dt><span data-portal-i18n="Fee">{{ __('Fee') }}</span></dt><dd><span data-portal-i18n="{{ $certificateType->feeLabel() }}">{{ __($certificateType->feeLabel()) }}</span>@if($certificateType === App\CertificateType::BusinessClearance) <span class="tag-unconfirmed"><span data-portal-i18n="To be confirmed">{{ __('To be confirmed') }}</span></span>@endif</dd></div>
                @endif
            </dl>
            @if($certificateType && $certificateType->fee() > 0)<p class="v2-summary-note"><span data-portal-i18n="Babayaran sa Barangay Hall pagkuha ng dokumento.">{{ __('Babayaran sa Barangay Hall pagkuha ng dokumento.') }}</span></p>@endif
        </section>
    </aside>
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/reference-copy.js') }}?v={{ filemtime(public_path('js/reference-copy.js')) }}"></script>
@endpush
