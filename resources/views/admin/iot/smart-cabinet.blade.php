@extends('layouts.admin-iot')
@section('title', 'Smart Cabinet')
@section('content')
<div class="eyebrow">Administration / IoT Devices</div>
<div class="page-heading"><div><h1>Smart Cabinet</h1><p>Device status, last contact, component health, and reported cabinet state.</p></div><span class="pill neutral">Read-only status</span></div>
<div class="notice">Hardware integration is pending. This page cannot unlock a cabinet, control a drawer, or infer that a device is online without a recent verified report.</div>
<section class="panel"><div class="panel-heading"><h2>Registered cabinets</h2><span>{{ $cabinets->total() }} registered</span></div>
@forelse($cabinets as $cabinet)
    <article class="device-card"><div><h3>{{ $cabinet->name }}</h3><p>{{ $cabinet->identifier }}</p></div><span class="pill {{ $cabinet->connectionStatus() === 'online' ? 'success' : 'neutral' }}">{{ ucfirst($cabinet->connectionStatus()) }}</span>
        <dl class="detail-grid"><div><dt>Last seen</dt><dd>{{ $cabinet->last_seen_at?->format('M j, Y g:i A') ?? 'Never reported' }}</dd></div><div><dt>Software</dt><dd>{{ $cabinet->software_version ?? 'Not reported' }}</dd></div><div><dt>Component health</dt><dd>{{ $cabinet->component_health ? json_encode($cabinet->component_health, JSON_UNESCAPED_SLASHES) : 'Not reported' }}</dd></div><div><dt>Cabinet state</dt><dd>{{ $cabinet->cabinet_state ? json_encode($cabinet->cabinet_state, JSON_UNESCAPED_SLASHES) : 'Not reported' }}</dd></div><div><dt>Last error</dt><dd>{{ $cabinet->last_error ?? 'None reported' }}</dd></div></dl></article>
@empty
    <div class="empty-state"><strong>No cabinets registered</strong><p>Device details will appear after an administrator registers the Raspberry Pi integration.</p></div>
@endforelse
<div class="pagination">{{ $cabinets->links() }}</div></section>
@endsection
