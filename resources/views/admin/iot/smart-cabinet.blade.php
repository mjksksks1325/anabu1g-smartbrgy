@extends('layouts.admin-iot')
@section('title', 'Smart Cabinet')
@section('content')
<div class="eyebrow">Administration / IoT Devices</div>
<div class="page-heading"><div><h1>Smart Cabinet</h1><p>Device status, last contact, component health, and reported cabinet state.</p></div><span class="pill neutral">Read-only status</span></div>
<div class="notice">Hardware integration is pending. This page cannot unlock a cabinet, control a drawer, or infer that a device is online without a recent verified report.</div>
<section class="panel"><div class="panel-heading"><h2>Registered cabinets</h2><span>{{ $cabinets->total() }} registered</span></div>
@forelse($cabinets as $cabinet)
    <article class="device-card"><div><h3>{{ $cabinet->name }}</h3><p>{{ $cabinet->identifier }}</p></div><span class="pill {{ $cabinet->connectionStatus() === 'online' ? 'success' : 'neutral' }}">{{ ucfirst($cabinet->connectionStatus()) }}</span>
        <dl class="detail-grid"><div><dt>Last seen</dt><dd>{{ $cabinet->last_seen_at?->format('M j, Y g:i A') ?? 'Never reported' }}</dd></div><div><dt>Software</dt><dd>{{ $cabinet->software_version ?? 'Not reported' }}</dd></div><div class="component-health-field"><dt>Component health</dt><dd>@php $healthItems = $cabinet->componentHealthItems(); @endphp
            @if ($healthItems)
                <p class="component-health-summary">{{ collect($healthItems)->where('tone', 'success')->count() }} of {{ count($healthItems) }} components healthy</p>
                <ul class="component-health-list">
                    @foreach ($healthItems as $item)
                        <li class="component-health-item is-{{ $item['tone'] }}"><span class="component-health-dot" aria-hidden="true"></span><strong>{{ $item['label'] }}</strong><span class="pill {{ $item['tone'] }}">{{ $item['status'] }}</span></li>
                    @endforeach
                </ul>
            @else
                Not reported
            @endif
        </dd></div><div><dt>Cabinet state</dt><dd>{{ $cabinet->cabinet_state ? json_encode($cabinet->cabinet_state, JSON_UNESCAPED_SLASHES) : 'Not reported' }}</dd></div><div><dt>Last error</dt><dd>{{ $cabinet->last_error ?? 'None reported' }}</dd></div></dl></article>
@empty
    <div class="empty-state"><strong>No cabinets registered</strong><p>Device details will appear after an administrator registers the Raspberry Pi integration.</p></div>
@endforelse
<div class="pagination">{{ $cabinets->links() }}</div></section>

<section class="panel cabinet-reader-panel" aria-labelledby="cabinet-readers-title">
    <div class="panel-heading"><h2 id="cabinet-readers-title">Folder reader mapping</h2><span>2 YRM100 units · 1 per drawer</span></div>
    @forelse($cabinets as $cabinet)
        @php($drawerOneReader = $cabinet->readers->firstWhere('drawer_reference', App\Models\CabinetFile::DRAWER_1))
        @php($drawerTwoReader = $cabinet->readers->firstWhere('drawer_reference', App\Models\CabinetFile::DRAWER_2))
        @php($readerAliasPrefix = Illuminate\Support\Str::slug($cabinet->identifier))
        <article class="cabinet-reader-card">
            <div class="cabinet-reader-heading"><div><h3>{{ $cabinet->name }}</h3><p>{{ $cabinet->identifier }} · Reader connection awaits Raspberry Pi code</p></div><span class="pill neutral">Not connected</span></div>
            <form class="filter-panel cabinet-reader-form" method="POST" action="{{ route('admin.smart-cabinet.readers.update', $cabinet) }}">
                @csrf
                @method('PATCH')
                <label>Drawer 1 reader alias<input name="drawer_1_reader_identifier" required maxlength="80" pattern="[A-Za-z0-9][A-Za-z0-9._-]*" value="{{ old('drawer_1_reader_identifier', $drawerOneReader?->reader_identifier ?? $readerAliasPrefix.'-d1') }}" autocomplete="off"></label>
                <label>Drawer 2 reader alias<input name="drawer_2_reader_identifier" required maxlength="80" pattern="[A-Za-z0-9][A-Za-z0-9._-]*" value="{{ old('drawer_2_reader_identifier', $drawerTwoReader?->reader_identifier ?? $readerAliasPrefix.'-d2') }}" autocomplete="off"></label>
                <button class="button" type="submit">Save reader mapping</button>
            </form>
            <p class="cabinet-reader-help">These are software aliases assigned to the two modules, not hardware serial numbers. The RPi code must report the matching alias so the website can identify the drawer.</p>
        </article>
    @empty
        <div class="empty-state"><strong>No cabinet available</strong><p>Register a cabinet before mapping the two folder readers.</p></div>
    @endforelse
</section>

<section class="panel cabinet-folder-panel" id="cabinet-folder-registry" aria-labelledby="cabinet-folder-title">
    <div class="panel-heading"><h2 id="cabinet-folder-title">Folder registry</h2><span>{{ $files->total() }} {{ \Illuminate\Support\Str::plural('folder', $files->total()) }}</span></div>
    <div class="cabinet-folder-intro">
        <p>Register folder details and assign each folder to its cabinet drawer. RFID enrollment is intentionally unavailable until the Raspberry Pi integration can confirm a real YRM100 scan.</p>
        <a class="button quiet" href="{{ route('admin.smart-cabinet.index', ['folder_status' => $folderStatus === 'active' ? 'archived' : 'active']) }}#cabinet-folder-registry">{{ $folderStatus === 'active' ? 'View archived folders' : 'View active folders' }}</a>
    </div>
    @if($cabinets->total() > 0)
        <form class="filter-panel cabinet-folder-create" method="POST" action="{{ route('admin.smart-cabinet.folders.store') }}">
            @csrf
            <label>Folder reference<input name="file_reference" required maxlength="100" pattern="[A-Za-z0-9][A-Za-z0-9._/-]*" value="{{ old('file_reference') }}" placeholder="e.g. DOC-2026-001" autocomplete="off"></label>
            <label>Folder name<input name="file_name" required maxlength="255" value="{{ old('file_name') }}" placeholder="Folder name" autocomplete="off"></label>
            <label>Cabinet<select name="cabinet_device_id" required>@foreach($cabinetOptions as $cabinetOption)<option value="{{ $cabinetOption->id }}" @selected(old('cabinet_device_id') == $cabinetOption->id)>{{ $cabinetOption->name }}</option>@endforeach</select></label>
            <label>Drawer<select name="drawer_reference" required><option value="drawer_1" @selected(old('drawer_reference', 'drawer_1') === 'drawer_1')>Drawer 1</option><option value="drawer_2" @selected(old('drawer_reference') === 'drawer_2')>Drawer 2</option></select></label>
            <button class="button" type="submit">Add folder</button>
        </form>
    @else
        <div class="empty-state"><strong>No cabinet available</strong><p>Register a cabinet before adding folders to the registry.</p></div>
    @endif

    @if($files->isEmpty())
        <div class="empty-state"><strong>No {{ $folderStatus }} folders registered</strong><p>Folder movement logs will link to these records after the RPi enrollment flow is connected.</p></div>
    @else
        <div class="table-scroll">
            <table class="cabinet-folder-table">
                <thead><tr><th>Reference</th><th>Folder name, cabinet &amp; drawer</th><th>RFID enrollment</th><th>Record status</th><th>Actions</th></tr></thead>
                <tbody>
                @foreach($files as $file)
                    <tr>
                        <td><strong>{{ $file->file_reference }}</strong></td>
                        <td>
                            <form class="cabinet-folder-edit" method="POST" action="{{ route('admin.smart-cabinet.folders.update', $file) }}">
                                @csrf
                                @method('PATCH')
                                <input name="file_name" aria-label="Folder name for {{ $file->file_reference }}" required maxlength="255" value="{{ $file->file_name }}">
                                <select name="cabinet_device_id" aria-label="Cabinet for {{ $file->file_reference }}" required>@foreach($cabinetOptions as $cabinetOption)<option value="{{ $cabinetOption->id }}" @selected($file->cabinet_device_id === $cabinetOption->id)>{{ $cabinetOption->name }}</option>@endforeach</select>
                                <select name="drawer_reference" aria-label="Drawer for {{ $file->file_reference }}" required><option value="drawer_1" @selected($file->drawer_reference === 'drawer_1')>Drawer 1</option><option value="drawer_2" @selected($file->drawer_reference === 'drawer_2')>Drawer 2</option></select>
                                <button class="button quiet" type="submit">Save</button>
                            </form>
                        </td>
                        <td><span class="pill neutral">{{ $file->isRfidEnrolled() ? 'Enrolled' : 'Awaiting RPi enrollment' }}</span></td>
                        <td><span class="pill {{ $file->is_active ? 'success' : 'neutral' }}">{{ $file->is_active ? 'Active' : 'Archived' }}</span></td>
                        <td>
                            @if ($file->is_active)
                                <button class="button quiet" type="button" data-folder-archive data-archive-url="{{ route('admin.smart-cabinet.folders.archive', $file) }}" data-file-reference="{{ $file->file_reference }}">Archive</button>
                            @else
                                <form method="POST" action="{{ route('admin.smart-cabinet.folders.restore', $file) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="button quiet" type="submit">Restore</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($files->hasPages())<div class="pagination cabinet-folder-pagination">{{ $files->links() }}</div>@endif
    @endif
</section>
<dialog class="cabinet-folder-archive-dialog" id="cabinet-folder-archive-dialog" aria-labelledby="cabinet-folder-archive-title" aria-describedby="cabinet-folder-archive-description">
    <form data-folder-archive-form>
        <h2 id="cabinet-folder-archive-title">Archive folder</h2>
        <p id="cabinet-folder-archive-description">This keeps the folder and its movement history in the records. Confirm with the active Barangay Captain or Super Admin password.</p>
        <p class="cabinet-folder-archive-reference" data-folder-archive-reference></p>
        <label for="cabinet-folder-archive-password">Password ng Barangay Captain o Super Admin</label>
        <input id="cabinet-folder-archive-password" name="super_admin_password" type="password" autocomplete="current-password" required>
        <p class="cabinet-folder-archive-error" data-folder-archive-error role="alert" hidden></p>
        <div class="cabinet-folder-archive-actions">
            <button class="button quiet" type="button" data-folder-archive-cancel>Cancel</button>
            <button class="button danger" type="submit" data-folder-archive-submit>Confirm archive</button>
        </div>
    </form>
</dialog>
@endsection
