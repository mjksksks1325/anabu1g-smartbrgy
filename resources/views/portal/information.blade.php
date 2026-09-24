@extends('layouts.portal')
@section('title', 'Barangay information and services')
@section('content')
<section class="card"><div class="card-header"><h2>Barangay Anabu I-G</h2><p>City of Imus, Cavite</p></div><div class="card-body"><p>Browse services here without an account. A verified resident account is required to submit an online document request.</p></div></section>
<section class="card" id="officials"><div class="card-header"><h2>Barangay officials</h2></div><div class="card-body"><p>The current officials directory is awaiting barangay confirmation. Please contact the barangay office for the official list.</p></div></section>
<section class="card" id="requirements"><div class="card-header"><h2>Services and document requirements</h2></div><div class="card-body resident-service-list">
@foreach($services as $service)
<article class="resident-request-row"><h3>{{ $service->value }}</h3><p>Submit an online request for {{ $service->value }} for barangay review.</p><p><strong>Fee:</strong> {{ $service->fee() === 0 ? 'Free' : 'PHP '.number_format($service->fee(), 2) }} (current system fee; confirm with the barangay).</p><p><strong>Requirements and processing:</strong> Please ask the barangay office to confirm the official requirements and processing time for this service.</p><a class="btn btn-green" href="{{ route('portal.request.create', ['service' => $service->portalCode()]) }}">Request this document</a></article>
@endforeach
</div></section>
<section class="card" id="help"><div class="card-header"><h2>Contact and assistance</h2></div><div class="card-body"><p>Visit Barangay Anabu I-G, City of Imus, Cavite for help with your resident record or online account.</p><p>Contact numbers, office hours, documentary requirements, and current service policies are awaiting official barangay confirmation.</p></div></section>
@endsection
