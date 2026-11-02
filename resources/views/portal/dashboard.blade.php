@extends('layouts.portal')
@section('title', __('Dashboard'))
@php
    $stages = ['pending', 'processing', 'approved', 'ready_for_release', 'released'];
    $totalRequests = $statusCounts->sum();
    $activeRequests = ($statusCounts['pending'] ?? 0) + ($statusCounts['processing'] ?? 0) + ($statusCounts['approved'] ?? 0);
    $readyCount = $statusCounts['ready_for_release'] ?? 0;
@endphp
@section('band')
<x-portal.page-band title="Magandang araw, :name." :title-params="['name' => $resident->first_name]" eyebrow="Dashboard" title-id="dashboard-title">
    <p><span data-portal-i18n="Narito ang mga kailangan ninyong malaman tungkol sa inyong mga request.">{{ __('Narito ang mga kailangan ninyong malaman tungkol sa inyong mga request.') }}</span></p>
</x-portal.page-band>
@endsection
@section('content')
<div class="rp-home" data-resident-private>
    <div class="rp-home-main">
        @if($attentionRequests->isNotEmpty() || ! $resident->has_photo)
            <section class="rp-attention" aria-labelledby="attention-title">
                <h2 id="attention-title"><span data-portal-i18n="Kailangan ng pansin">{{ __('Kailangan ng pansin') }}</span></h2>
                <ul>
                    @foreach($attentionRequests as $item)
                        @php
                            $message = match ($item->status) {
                                'ready_for_release' => 'Puwede nang kunin. Dalhin ang original na valid ID at ang reference number.',
                                'rejected' => 'Hindi naaprubahan. Tingnan ang dahilan at kung ano ang puwedeng gawin.',
                                default => 'May paalala mula sa barangay. Basahin ito bago pumunta.',
                            };
                        @endphp
                        <li data-status="{{ $item->status }}">
                            <a href="{{ route('portal.account.requests.show', $item->reference_code) }}">
                                <span class="rp-attention-doc"><strong><span data-portal-i18n="{{ $item->document_type }}">{{ __($item->document_type) }}</span></strong> <span class="v2-ref">{{ $item->reference_code }}</span></span>
                                <span class="rp-attention-message"><span data-portal-i18n="{{ $message }}">{{ __($message) }}</span></span>
                                <span class="rp-attention-link"><span data-portal-i18n="Tingnan ang detalye">{{ __('Tingnan ang detalye') }}</span></span>
                            </a>
                        </li>
                    @endforeach
                    @unless($resident->has_photo)
                        <li data-status="photo">
                            <a href="{{ route('portal.profile') }}#resident-photo">
                                <span class="rp-attention-doc"><strong><span data-portal-i18n="Resident photo">{{ __('Resident photo') }}</span></strong></span>
                                <span class="rp-attention-message"><span data-portal-i18n="Wala pang resident photo. Mag-upload ng malinaw na larawan ng inyong mukha.">{{ __('Wala pang resident photo. Mag-upload ng malinaw na larawan ng inyong mukha.') }}</span></span>
                                <span class="rp-attention-link"><span data-portal-i18n="Mag-upload ng photo">{{ __('Mag-upload ng photo') }}</span></span>
                            </a>
                        </li>
                    @endunless
                </ul>
            </section>
        @endif

        <nav class="rp-actions" aria-label="{{ __('Online services') }}" data-portal-i18n-aria-label="Online services">
            <a class="rp-action rp-action-primary" href="{{ route('portal.request.create') }}">
                <span class="rp-action-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M12 11v6M9 14h6"/></svg></span>
                <span class="rp-action-title"><span data-portal-i18n="Request a document">{{ __('Request a document') }}</span></span>
                <span class="rp-action-text"><span data-portal-i18n="Barangay Clearance, Residency, Indigency, Barangay ID at iba pa.">{{ __('Barangay Clearance, Residency, Indigency, Barangay ID at iba pa.') }}</span></span>
            </a>
            <a class="rp-action" href="{{ route('portal.account') }}">
                <span class="rp-action-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="12" height="17" rx="2"/><path d="M9 4V3h6v1M9 10h6M9 14h6M9 18h3"/></svg></span>
                <span class="rp-action-title"><span data-portal-i18n="My requests">{{ __('My requests') }}</span></span>
                <span class="rp-action-text">
                    @if($totalRequests === 0)
                        <span data-portal-i18n="Wala pang request">{{ __('Wala pang request') }}</span>
                    @else
                        <span data-portal-i18n=":active pinoproseso · :ready handa nang kunin" data-portal-params="{{ json_encode(['active' => $activeRequests, 'ready' => $readyCount]) }}">{{ __(':active pinoproseso · :ready handa nang kunin', ['active' => $activeRequests, 'ready' => $readyCount]) }}</span>
                    @endif
                </span>
            </a>
        </nav>

        <section class="rp-panel" aria-labelledby="recent-title">
            <div class="rp-panel-head">
                <h2 id="recent-title"><span data-portal-i18n="Mga huling request">{{ __('Mga huling request') }}</span></h2>
                @if($totalRequests > 0)<a href="{{ route('portal.account') }}"><span data-portal-i18n="Tingnan ang lahat ng request">{{ __('Tingnan ang lahat ng request') }}</span></a>@endif
            </div>
            @if($recentRequests->isEmpty())
                <div class="rp-first-time">
                    <p class="rp-first-time-lead"><strong><span data-portal-i18n="Wala pa kayong request. Ganito ang pag-request:">{{ __('Wala pa kayong request. Ganito ang pag-request:') }}</span></strong></p>
                    <ol class="rp-first-steps">
                        <li><strong><span data-portal-i18n="Piliin ang dokumento">{{ __('Piliin ang dokumento') }}</span></strong><span data-portal-i18n="Makikita ang fee at kung gaano katagal itong valid.">{{ __('Makikita ang fee at kung gaano katagal itong valid.') }}</span></li>
                        <li><strong><span data-portal-i18n="Ilagay ang layunin at i-submit">{{ __('Ilagay ang layunin at i-submit') }}</span></strong><span data-portal-i18n="Bibigyan kayo ng reference number.">{{ __('Bibigyan kayo ng reference number.') }}</span></li>
                        <li><strong><span data-portal-i18n="Kunin sa Barangay Hall">{{ __('Kunin sa Barangay Hall') }}</span></strong><span data-portal-i18n="Kapag Ready for release na. Dalhin ang valid ID at ang reference number.">{{ __('Kapag Ready for release na. Dalhin ang valid ID at ang reference number.') }}</span></li>
                    </ol>
                    <a class="btn btn-green" href="{{ route('portal.request.create') }}"><span data-portal-i18n="Simulan ang unang request">{{ __('Simulan ang unang request') }}</span></a>
                </div>
            @else
                <ul class="rp-recent">
                    @foreach($recentRequests as $item)
                        @php
                            $stageIndex = array_search($item->status, $stages, true);
                            $next = config('portal.request_statuses.'.$item->status.'.next');
                        @endphp
                        <li data-status="{{ $item->status }}">
                            <a href="{{ route('portal.account.requests.show', $item->reference_code) }}">
                                <span class="rp-recent-top">
                                    <span class="rp-recent-doc"><strong><span data-portal-i18n="{{ $item->document_type }}">{{ __($item->document_type) }}</span></strong><span class="v2-ref">{{ $item->reference_code }}</span></span>
                                    @include('portal.partials.request-status-pill', ['status' => $item->status])
                                </span>
                                @if($stageIndex !== false)
                                    <span class="rp-progress" role="img" aria-label="{{ __('Yugto :current sa :total', ['current' => $stageIndex + 1, 'total' => count($stages)]) }}" data-portal-i18n-aria-label="Yugto :current sa :total">
                                        @foreach($stages as $index => $stage)<span @class(['is-done' => $index <= $stageIndex])></span>@endforeach
                                    </span>
                                @endif
                                @if($next)<span class="rp-recent-next"><strong><span data-portal-i18n="Susunod:">{{ __('Susunod:') }}</span></strong> <span data-portal-i18n="{{ $next }}">{{ __($next) }}</span></span>@endif
                                <span class="rp-recent-date"><span data-portal-i18n="Na-submit">{{ __('Na-submit') }}</span> <time datetime="{{ $item->created_at->toDateString() }}">{{ $item->created_at->format('M j, Y') }}</time></span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <aside class="rp-home-side" aria-label="{{ __('Kailangan ng tulong?') }}" data-portal-i18n-aria-label="Kailangan ng tulong?">
        <section class="rp-panel" aria-labelledby="help-title">
            <h2 id="help-title"><span data-portal-i18n="Kailangan ng tulong?">{{ __('Kailangan ng tulong?') }}</span></h2>
            <ul class="v2-links">
                <li><a href="{{ route('portal.information') }}#faq"><span data-portal-i18n="Mga madalas itanong">{{ __('Mga madalas itanong') }}</span></a></li>
                <li><a href="{{ route('portal.information') }}#requirements"><span data-portal-i18n="Requirements and fees">{{ __('Requirements and fees') }}</span></a></li>
                <li><a href="{{ route('portal.information') }}#help"><span data-portal-i18n="Problema sa account o record">{{ __('Problema sa account o record') }}</span></a></li>
            </ul>
        </section>
        <section class="rp-panel rp-panel-info" aria-labelledby="visit-title">
            <h2 id="visit-title"><span data-portal-i18n="Bago pumunta sa Barangay Hall">{{ __('Bago pumunta sa Barangay Hall') }}</span></h2>
            <ul class="v2-list">
                <li><span data-portal-i18n="Hintaying maging Ready for release ang request bago pumunta.">{{ __('Hintaying maging Ready for release ang request bago pumunta.') }}</span></li>
                <li><span data-portal-i18n="Dalhin ang original na valid ID at ang reference number.">{{ __('Dalhin ang original na valid ID at ang reference number.') }}</span></li>
                <li><span data-portal-i18n="Office hours:">{{ __('Office hours:') }}</span> <span class="tag-unconfirmed"><span data-portal-i18n="Hindi pa kumpirmado">{{ __('Hindi pa kumpirmado') }}</span></span></li>
            </ul>
        </section>
    </aside>
</div>
@endsection
