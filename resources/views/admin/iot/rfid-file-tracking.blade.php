@extends('layouts.admin-iot')
@section('title', 'RFID File Tracking')
@section('content')
<div class="eyebrow">IoT Security / File Movement</div>
<div class="page-heading"><div><h1>RFID File Tracking</h1><p>Recorded movements of physical files. Each event shows who removed or returned a file, when, and where.</p></div></div>
<div class="notice">RFID scans and cabinet events will appear here after trusted Raspberry Pi integration is connected. No scan can be simulated from this page.</div>
<div class="iot-rfid-content">
    <section class="panel" data-filter-results aria-busy="false">
        <div class="panel-heading"><h2>Movement history</h2><span data-filter-count>{{ $movements->total() }} recorded {{ \Illuminate\Support\Str::plural('event', $movements->total()) }}</span></div>
        <form class="filter-panel iot-rfid-filters" method="GET" action="{{ route('staff.rfid-files.index') }}" role="search" aria-label="Filter file movements" data-auto-filter>
            <label class="iot-filter-search">Search file, RFID tag, or employee<input type="search" name="search" autocomplete="off" value="{{ $filters['search'] ?? '' }}" placeholder="File reference, RFID tag, or employee"></label>
            <label>Action<select name="action"><option value="">All actions</option><option value="removed" @selected(($filters['action'] ?? '') === 'removed')>Removed</option><option value="returned" @selected(($filters['action'] ?? '') === 'returned')>Returned</option></select></label>
            <label>Date<input type="date" name="date" value="{{ $filters['date'] ?? '' }}"></label>
            <label>Cabinet<select name="cabinet"><option value="">All cabinets</option>@foreach($cabinets as $cabinet)<option value="{{ $cabinet->id }}" @selected(($filters['cabinet'] ?? null) == $cabinet->id)>{{ $cabinet->name }}</option>@endforeach</select></label>
            <label>Drawer<input type="search" name="drawer" autocomplete="off" value="{{ $filters['drawer'] ?? '' }}" placeholder="Drawer ID"></label>
        </form>
        <div data-filter-region>
        @if($movements->isEmpty())
            @if(array_filter($filters))
                <div class="empty-state"><strong>No movements match these filters</strong><p>Try another file, tag or employee, or clear a filter.</p></div>
            @else
                <div class="empty-state"><strong>No file movements recorded</strong><p>Events will show here when a connected device reports a verified removal or return.</p></div>
            @endif
        @else
            <div class="table-scroll"><table class="iot-movement-table"><thead><tr><th>When</th><th>File</th><th>Action</th><th>Current status</th><th>Employee</th><th>Cabinet / drawer</th><th>RFID tag</th></tr></thead><tbody>
            @foreach($movements as $movement)
                <tr><td><time datetime="{{ $movement->occurred_at->toIso8601String() }}">{{ $movement->occurred_at->format('M j, Y') }}<small>{{ $movement->occurred_at->format('g:i A') }}</small></time></td><td><strong>{{ $movement->file_name ?: $movement->file_reference }}</strong><small>{{ $movement->file_reference }}</small></td><td><span class="pill {{ $movement->action === 'removed' ? 'warning' : 'success' }}">{{ ucfirst($movement->action) }}</span></td><td><span class="pill {{ $movement->current_action === 'returned' ? 'success' : ($movement->current_action === 'removed' ? 'warning' : 'neutral') }}">{{ $movement->current_action === 'returned' ? 'In cabinet' : ($movement->current_action === 'removed' ? 'Checked out' : 'Unknown') }}</span></td><td>{{ $movement->user?->name ?? 'Unknown employee' }}</td><td>{{ $movement->cabinetDevice?->name ?? 'Not recorded' }}<small>{{ $movement->drawer_reference ?? 'Drawer not recorded' }}</small></td><td><code>{{ $movement->rfid_tag ?? 'Not recorded' }}</code></td></tr>
            @endforeach
            </tbody></table></div>
            @if($movements->hasPages())<div class="pagination iot-rfid-pagination">@include('partials.staff-pager', ['paginator' => $movements, 'singular' => 'event', 'plural' => 'events'])</div>@endif
        @endif
        </div>
    </section>
</div>
@endsection
