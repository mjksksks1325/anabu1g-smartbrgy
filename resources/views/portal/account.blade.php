@extends('layouts.portal')
@section('title', __('My requests'))
@section('band')
<x-portal.page-band title="My requests">
    <p data-resident-private><span data-portal-i18n="Mga document request na naka-link sa resident record ni">{{ __('Mga document request na naka-link sa resident record ni') }}</span> {{ $resident->full_name }}.</p>
    @if($requests->isNotEmpty())
        <p><span data-portal-i18n="Pindutin ang isang request para makita ang buong status.">{{ __('Pindutin ang isang request para makita ang buong status.') }}</span></p>
    @endif
    <x-slot:actions>
        <a class="btn btn-green" href="{{ route('portal.request.create') }}"><span aria-hidden="true">+</span> <span data-portal-i18n="Request a document">{{ __('Request a document') }}</span></a>
    </x-slot:actions>
</x-portal.page-band>
@endsection
@section('content')
<section data-resident-private aria-labelledby="page-title">
    @if($requests->isEmpty())
        <div class="v2-card v2-empty">
            <h2><span data-portal-i18n="Wala pang request">{{ __('Wala pang request') }}</span></h2>
            <p><span data-portal-i18n="Pagka-submit ng request, dito makikita ang reference number, status, at kung puwede na itong kunin sa Barangay Hall.">{{ __('Pagka-submit ng request, dito makikita ang reference number, status, at kung puwede na itong kunin sa Barangay Hall.') }}</span></p>
            <a class="btn btn-green" href="{{ route('portal.request.create') }}"><span data-portal-i18n="Request a document">{{ __('Request a document') }}</span></a>
        </div>
    @else
        <p class="list-caption">{{ $requests->total() }} <span data-portal-i18n="{{ $requests->total() === 1 ? 'request' : 'requests' }}">{{ __($requests->total() === 1 ? 'request' : 'requests') }}</span> <span data-portal-i18n="· Pinakabago ang nasa itaas">{{ __('· Pinakabago ang nasa itaas') }}</span></p>
        <div class="request-table">
            <div class="request-table-head" aria-hidden="true"><span><span data-portal-i18n="Dokumento">{{ __('Dokumento') }}</span></span><span><span data-portal-i18n="Na-submit">{{ __('Na-submit') }}</span></span><span><span data-portal-i18n="Status">{{ __('Status') }}</span></span><span></span></div>
            <ol class="request-list" data-request-history-url="{{ route('portal.account.statuses') }}">
                @foreach($requests as $item)
                    @include('portal.partials.request-history-item', ['item' => $item])
                @endforeach
            </ol>
        </div>
        {{ $requests->links('portal.partials.pagination') }}
    @endif
</section>
@endsection
