@extends('layouts.portal')
@section('title', 'My requests')
@section('band')
<x-portal.page-band title="My requests">
    <div data-resident-private>
        <p>Mga document request na naka-link sa resident record ni {{ $resident->full_name }}.</p>
        <p class="resident-number">Resident number <strong>{{ $resident->resident_number }}</strong></p>
    </div>
    @if($requests->isNotEmpty())
        <x-slot:actions>
            <a class="btn btn-green" href="{{ route('portal.request.create') }}">Request a document</a>
        </x-slot:actions>
    @endif
</x-portal.page-band>
@endsection
@section('content')
<section data-resident-private aria-labelledby="page-title">

    @if($requests->isEmpty())
        <div class="panel empty-state">
            <h2>Wala pang request</h2>
            <p>Pagka-submit ng request, dito makikita ang reference number, status, at kung puwede na itong kunin sa Barangay Hall.</p>
            <a class="btn btn-green" href="{{ route('portal.request.create') }}">Request a document</a>
        </div>
    @else
        <p class="list-caption">{{ $requests->total() }} {{ $requests->total() === 1 ? 'request' : 'requests' }} &middot; Pinakabago ang nasa itaas</p>
        <ol class="request-list" data-request-history-url="{{ route('portal.account.statuses') }}">
            @foreach($requests as $item)
                @include('portal.partials.request-history-item', ['item' => $item])
            @endforeach
        </ol>
        {{ $requests->links('pagination::simple-bootstrap-5') }}
    @endif
</section>
@endsection
@push('scripts')
<script src="{{ asset('js/request-history.js') }}?v={{ filemtime(public_path('js/request-history.js')) }}"></script>
@endpush
