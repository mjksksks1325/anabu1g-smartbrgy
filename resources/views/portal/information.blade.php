@extends('layouts.portal')
@section('title', 'Requirements and fees')
@section('band')
<x-portal.page-band title="Requirements and fees">
    <p>Mga dokumentong puwedeng i-request online, ang fee na nasa system, at kung saan hihingi ng tulong. Puwede itong basahin kahit walang account.</p>
    <x-slot:below>
        <nav class="jump-links" aria-label="Sa page na ito">
            <a href="#requirements">Mga dokumento</a>
            <a href="#help">Get help</a>
            <a href="#officials">Barangay officials</a>
        </nav>
    </x-slot:below>
</x-portal.page-band>
@endsection
@section('content')
<section class="section section-first" id="requirements" aria-labelledby="requirements-title">
    <div class="section-head"><h2 id="requirements-title">Mga dokumento</h2></div>
    <div class="requirements-intro">
        <div class="panel panel-tint">
            <h3>Para sa lahat ng dokumento</h3>
            <ul class="plain-list">
                <li>Kailangan ng resident account para mag-request online.</li>
                <li>Nire-review ng barangay staff ang bawat request. May mga dokumentong posibleng mangailangan ng dagdag na verification bago i-release.</li>
                <li>Sa Barangay Hall kukunin ang dokumento. Dalhin ang valid ID at ang reference number.</li>
                <li>Sa Barangay Hall babayaran ang fee. Valid ang online request nang 30 araw mula sa pag-submit.</li>
            </ul>
        </div>
        <p class="unconfirmed"><strong>Hindi pa kumpirmado:</strong> Ang fees sa ibaba ay mula sa system. Hindi pa rin kumpirmado ng barangay ang eksaktong requirements at processing time ng bawat dokumento. Magtanong sa Barangay Hall bago kumuha.</p>
    </div>
    <div class="service-table">
    <div class="service-list-head" aria-hidden="true"><span>Dokumento</span><span>Fee</span><span></span></div>
    <ul class="service-list">
        @foreach($services as $service)
            <li class="service-item">
                <h3>{{ $service->value }}</h3>
                <p class="service-fee"><span class="service-fee-label">Fee: </span>{{ $service->feeLabel() }}</p>
                <a class="btn btn-outline btn-small" href="{{ route('portal.request.create', ['service' => $service->portalCode()]) }}" aria-label="Request {{ $service->value }}">Request</a>
            </li>
        @endforeach
    </ul>
    </div>
</section>

<section class="section" id="help" aria-labelledby="help-title">
    <div class="section-head"><h2 id="help-title">Get help</h2></div>
    <div class="two-column">
        <div class="panel">
            <h3>Problema sa account o record</h3>
            <p>Pumunta sa Barangay Hall at dalhin ang valid ID. Matutulungan kayo ng staff sa:</p>
            <ul class="plain-list help-list">
                <li>record na hindi mahanap sa <a href="{{ route('portal.register') }}">Create account</a></li>
                <li>maling pangalan, address, o contact number</li>
                <li>activation code kung hindi dumating ang email</li>
                <li>account na hindi magamit ang online services</li>
            </ul>
            <p class="next-note">Nakalimutan ang password? <a href="{{ route('password.request') }}">I-reset ang password</a>.</p>
        </div>
        <div class="panel panel-tint">
            <h3>Barangay office</h3>
            <dl class="office-list">
                <dt>Address</dt><dd>Barangay Anabu I-G, Lungsod ng Imus, Cavite</dd>
                <dt>Telepono</dt><dd><span class="tag-unconfirmed">Hindi pa kumpirmado</span></dd>
                <dt>Office hours</dt><dd><span class="tag-unconfirmed">Hindi pa kumpirmado</span></dd>
            </dl>
            <p class="next-note">Para sa emergency, tingnan ang <a href="{{ route('home') }}#contacts">emergency hotlines</a>.</p>
        </div>
    </div>
</section>

<section class="section" id="officials" aria-labelledby="officials-title">
    <div class="section-head"><h2 id="officials-title">Barangay officials</h2></div>
    <p class="unconfirmed"><strong>Hindi pa kumpirmado:</strong> Hinihintay pa ang opisyal na listahan mula sa barangay. Magtanong sa Barangay Hall para sa kasalukuyang mga opisyal.</p>
</section>
@endsection
