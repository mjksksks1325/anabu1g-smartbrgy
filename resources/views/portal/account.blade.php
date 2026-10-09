@extends('layouts.portal')
@section('title', __('My requests'))
@section('band')
<x-portal.page-band title="My requests">
    <div data-resident-private>
        <p><span data-portal-i18n="Mga document request na naka-link sa resident record ni">{{ __('Mga document request na naka-link sa resident record ni') }}</span> {{ $resident->full_name }}.</p>
        <p class="resident-number"><span data-portal-i18n="Resident number">{{ __('Resident number') }}</span> <strong>{{ $resident->resident_number }}</strong></p>
    </div>
    @if($requests->isNotEmpty())
        <x-slot:actions>
            <a class="btn btn-green" href="{{ route('portal.request.create') }}"><span data-portal-i18n="Request a document">{{ __('Request a document') }}</span></a>
        </x-slot:actions>
    @endif
</x-portal.page-band>
@endsection
@section('content')
<section data-resident-private aria-labelledby="page-title">

    @if($requests->isEmpty())
        <div class="panel empty-state">
            <h2><span data-portal-i18n="Wala pang request">{{ __('Wala pang request') }}</span></h2>
            <p><span data-portal-i18n="Pagka-submit ng request, dito makikita ang reference number, status, at kung puwede na itong kunin sa Barangay Hall.">{{ __('Pagka-submit ng request, dito makikita ang reference number, status, at kung puwede na itong kunin sa Barangay Hall.') }}</span></p>
            <a class="btn btn-green" href="{{ route('portal.request.create') }}"><span data-portal-i18n="Request a document">{{ __('Request a document') }}</span></a>
        </div>
    @else
        <p class="list-caption">{{ $requests->total() }} <span data-portal-i18n="{{ $requests->total() === 1 ? 'request' : 'requests' }}">{{ __($requests->total() === 1 ? 'request' : 'requests') }}</span> <span data-portal-i18n="· Pinakabago ang nasa itaas">{{ __('· Pinakabago ang nasa itaas') }}</span></p>
        <ol class="request-list" data-request-history-url="{{ route('portal.account.statuses') }}">
            @foreach($requests as $item)
                @include('portal.partials.request-history-item', ['item' => $item])
            @endforeach
        </ol>
        {{ $requests->links('portal.partials.pagination') }}
    @endif
</section>
@endsection
@push('scripts')
<script src="{{ asset('js/request-history.js') }}?v={{ filemtime(public_path('js/request-history.js')) }}"></script>
@endpush
