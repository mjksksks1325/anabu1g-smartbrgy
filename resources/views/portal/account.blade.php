@extends('layouts.portal')
@section('title', 'My requests')
@section('content')
<section data-resident-private><div class="portal-intro resident-account-intro"><div><div class="portal-eyebrow">Resident account</div><h2>My requests</h2><p>Welcome back, {{ $resident->full_name }}. Follow the progress of your document requests here.</p></div><div class="resident-account-number"><span>RESIDENT NUMBER</span><strong>{{ $resident->resident_number }}</strong></div></div>
<div class="resident-account-actions"><a class="btn btn-green" href="{{ route('portal.request.create') }}">Request a document &rarr;</a></div>
<div class="card"><div class="card-header"><h2>Document requests</h2><p>{{ $requests->total() }} {{ $requests->total() === 1 ? 'request' : 'requests' }} linked to your resident record.</p></div><div class="card-body resident-request-history">
@forelse($requests as $item)
<article class="resident-request-row"><div class="resident-request-row-heading"><div><div class="portal-eyebrow">{{ $item->reference_code }}</div><h3>{{ $item->document_type }}</h3></div><span class="resident-request-status">{{ ucfirst(str_replace('_', ' ', $item->status)) }}</span></div><p>Submitted {{ $item->created_at->format('M d, Y') }}</p>
@if($item->status === 'rejected' && $item->rejection_reason)<p class="alert alert-red">{{ $item->rejection_reason }}</p>@endif
@if($item->status === 'ready_for_release')<p>Ready for release. Please coordinate collection with the barangay office.</p>@endif
</article>
@empty<p>No requests are linked to your resident record yet.</p>@endforelse
{{ $requests->links() }}
</div></div></section>
@endsection
