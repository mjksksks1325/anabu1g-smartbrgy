@extends('layouts.portal')
@section('title', 'Account assistance')
@section('band')
<x-portal.page-band title="Hindi magamit ang online services" title-id="assistance-title" />
@endsection
@section('content')
<section class="card card-accent narrow" aria-labelledby="assistance-title">
    <div class="card-body stack">
        <p class="alert alert-yellow" role="alert">{{ $message }}</p>
        <p>Dalhin ang valid ID sa Barangay Hall para ma-check ng staff ang account at resident record ninyo.</p>
        <div class="btn-row"><a class="btn btn-outline" href="{{ route('portal.information') }}#help">Get help</a></div>
    </div>
</section>
@endsection
