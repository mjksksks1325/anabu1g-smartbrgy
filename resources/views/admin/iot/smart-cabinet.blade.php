@extends('layouts.admin-iot')
@section('title', 'Smart Cabinet')
@section('staff-ui', 'true')
@section('content')
@php
    $allErrors = $errors;
    $noErrors = new \Illuminate\Support\ViewErrorBag;
    // Each form names itself, so a failed save shows its errors and typed values in that form only.
    $failedForm = old('_form');
    $connectionTone = fn (string $status): string => match ($status) { 'online' => 'success', 'offline' => 'danger', default => 'neutral' };
@endphp
<div data-ui class="text-zinc-800 dark:text-zinc-100 space-y-6">
    <x-ui.page-header eyebrow="Administration / IoT Devices" title="Smart Cabinet" description="Device status, last contact, component health, and reported cabinet state.">
        <x-slot:actions>
            <x-ui.status-badge tone="neutral" icon="eye">Read-only status</x-ui.status-badge>
        </x-slot:actions>
    </x-ui.page-header>

    <flux:callout icon="information-circle" color="sky">
        <flux:callout.text>Hardware integration is pending. This page cannot unlock a cabinet, control a drawer, or infer that a device is online without a recent verified report.</flux:callout.text>
    </flux:callout>

    {{-- Registered cabinets: reported status only. --}}
    <x-ui.panel title="Registered cabinets" heading-id="registered-cabinets-title">
        <x-slot:meta>{{ $cabinets->total() }} registered</x-slot:meta>
        @forelse ($cabinets as $cabinet)
            @php($healthItems = $cabinet->componentHealthItems())
            <article class="border-b border-zinc-200 px-5 py-5 last:border-b-0 dark:border-zinc-700" data-cabinet-device="{{ $cabinet->id }}">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading level="3" size="lg" data-no-admin-translate>{{ $cabinet->name }}</flux:heading>
                        <flux:text class="font-mono text-xs">{{ $cabinet->identifier }}</flux:text>
                    </div>
                    <x-ui.status-badge :tone="$connectionTone($cabinet->connectionStatus())">{{ ucfirst($cabinet->connectionStatus()) }}</x-ui.status-badge>
                </div>
                <x-ui.details class="lg:grid-cols-4">
                    <x-ui.detail label="Last seen" class="tabular-nums">{{ $cabinet->last_seen_at?->format('M j, Y g:i A') ?? 'Never reported' }}</x-ui.detail>
                    <x-ui.detail label="Software">{{ $cabinet->software_version ?? 'Not reported' }}</x-ui.detail>
                    <x-ui.detail label="Last error">@if ($cabinet->last_error)<span data-no-admin-translate>{{ $cabinet->last_error }}</span>@else None reported @endif</x-ui.detail>
                    <x-ui.detail label="Cabinet state">
                        @if (is_array($cabinet->cabinet_state) && $cabinet->cabinet_state)
                            <ul class="cabinet-state-list">@foreach ($cabinet->cabinet_state as $stateKey => $stateValue)<li><span>{{ \Illuminate\Support\Str::headline((string) $stateKey) }}</span><strong>{{ is_bool($stateValue) ? ($stateValue ? 'Yes' : 'No') : (is_scalar($stateValue) ? $stateValue : json_encode($stateValue, JSON_UNESCAPED_SLASHES)) }}</strong></li>@endforeach</ul>
                        @elseif ($cabinet->cabinet_state)
                            {{ json_encode($cabinet->cabinet_state, JSON_UNESCAPED_SLASHES) }}
                        @else
                            Not reported
                        @endif
                    </x-ui.detail>
                    <x-ui.detail label="Component health" wide class="[overflow-wrap:normal]">
                        @if ($healthItems)
                            <p class="mb-2 text-xs text-zinc-500 dark:text-zinc-400">{{ collect($healthItems)->where('tone', 'success')->count() }} of {{ count($healthItems) }} components healthy</p>
                            <ul class="grid gap-1.5 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach ($healthItems as $item)
                                    <li class="flex items-center justify-between gap-3 rounded-md border border-zinc-200 px-3 py-1.5 dark:border-zinc-700">
                                        <span class="text-sm font-medium">{{ $item['label'] }}</span>
                                        <x-ui.status-badge :tone="$item['tone']">{{ $item['status'] }}</x-ui.status-badge>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            Not reported
                        @endif
                    </x-ui.detail>
                </x-ui.details>
            </article>
        @empty
            <x-ui.empty-state title="No cabinets registered" icon="server-stack">Device details will appear after an administrator registers the Raspberry Pi integration.</x-ui.empty-state>
        @endforelse
        @if ($cabinets->hasPages())
            <div class="border-t border-zinc-200 px-5 py-3 dark:border-zinc-700"><x-ui.pager :paginator="$cabinets" singular="cabinet" plural="cabinets" /></div>
        @endif
    </x-ui.panel>

    {{-- Folder reader mapping: one YRM100 alias per drawer. --}}
    <x-ui.panel title="Folder reader mapping" heading-id="cabinet-readers-title">
        <x-slot:meta>2 YRM100 units · 1 per drawer</x-slot:meta>
        @forelse ($cabinets as $cabinet)
            @php($drawerOneReader = $cabinet->readers->firstWhere('drawer_reference', App\Models\CabinetFile::DRAWER_1))
            @php($drawerTwoReader = $cabinet->readers->firstWhere('drawer_reference', App\Models\CabinetFile::DRAWER_2))
            @php($readerHealth = $cabinet->component_health ?? [])
            @php($drawerHealthItems = collect($cabinet->folderReaderHealthItems()))
            @php($bothReadersConnected = $drawerHealthItems->every(fn ($item) => $item['status'] === 'Connected'))
            @php($readerHealthSummary = $bothReadersConnected ? 'Both readers connected' : ($drawerHealthItems->contains('status', 'Not current') ? 'Reader status not current' : ($drawerHealthItems->contains('status', 'Not reported') ? 'Per-drawer status not reported' : 'Reader needs attention')))
            @php($failedHere = $failedForm === 'readers-'.$cabinet->id)
            @scopeErrors($failedHere ? $allErrors : $noErrors)
            <article class="space-y-4 border-b border-zinc-200 px-5 py-5 last:border-b-0 dark:border-zinc-700" data-cabinet-readers="{{ $cabinet->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading level="3" data-no-admin-translate>{{ $cabinet->name }}</flux:heading>
                        <flux:text class="text-xs"><span class="font-mono">{{ $cabinet->identifier }}</span> · YRM100 hardware status reported by Raspberry Pi</flux:text>
                    </div>
                    <x-ui.status-badge :tone="$bothReadersConnected ? 'success' : ($drawerHealthItems->contains('tone', 'warning') ? 'warning' : 'neutral')">{{ $readerHealthSummary }}</x-ui.status-badge>
                </div>
                <form class="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end" method="POST" action="{{ route('admin.smart-cabinet.readers.update', $cabinet) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="_form" value="readers-{{ $cabinet->id }}">
                    <flux:field>
                        <flux:label>Drawer 1 reader alias</flux:label>
                        <flux:input name="drawer_1_reader_identifier" required maxlength="80" pattern="[A-Za-z0-9][A-Za-z0-9._\-]*" :value="$failedHere ? old('drawer_1_reader_identifier') : ($drawerOneReader?->reader_identifier ?? 'reader-one')" autocomplete="off" />
                        <flux:error name="drawer_1_reader_identifier" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Drawer 2 reader alias</flux:label>
                        <flux:input name="drawer_2_reader_identifier" required maxlength="80" pattern="[A-Za-z0-9][A-Za-z0-9._\-]*" :value="$failedHere ? old('drawer_2_reader_identifier') : ($drawerTwoReader?->reader_identifier ?? 'reader-two')" autocomplete="off" />
                        <flux:error name="drawer_2_reader_identifier" />
                    </flux:field>
                    <flux:button type="submit" variant="primary">Save reader mapping</flux:button>
                </form>
                <ul class="grid gap-1.5 sm:grid-cols-2">
                    @foreach ($drawerHealthItems as $item)
                        @php($mappedReader = $cabinet->readers->firstWhere('drawer_reference', $item['drawer_reference']))
                        <li class="flex items-center justify-between gap-3 rounded-md border border-zinc-200 px-3 py-1.5 dark:border-zinc-700">
                            <span class="text-sm font-medium"><span>{{ $item['label'] }}</span> <span aria-hidden="true">·</span> <span class="font-mono text-xs">{{ $mappedReader?->reader_identifier ?? 'Not assigned' }}</span></span>
                            <x-ui.status-badge :tone="$item['tone']">{{ $item['status'] }}</x-ui.status-badge>
                        </li>
                    @endforeach
                </ul>
                <div class="space-y-1.5 text-sm text-zinc-600 dark:text-zinc-400">
                    @if ($cabinet->connectionStatus() === 'online' && ($readerHealth['folder_rfid'] ?? null) === 'connected' && $drawerHealthItems->contains('status', 'Not reported'))
                        <p>Folder RFID is connected overall. The Raspberry Pi has not reported a separate connection status for each drawer.</p>
                    @endif
                    @if (! $drawerOneReader || ! $drawerTwoReader)
                        <p class="text-amber-700 dark:text-amber-400">Reader mapping has not been saved for every drawer. Check the aliases configured on the Raspberry Pi, then select Save reader mapping. Prefilled values are suggestions.</p>
                    @endif
                    <p>These are software aliases assigned to the two modules, not hardware serial numbers. The RPi code must report the matching alias so the website can identify the drawer.</p>
                </div>
            </article>
        @empty
            <x-ui.empty-state title="No cabinet available" icon="server-stack">Register a cabinet before mapping the two folder readers.</x-ui.empty-state>
        @endforelse
        @scopeErrors($allErrors)
    </x-ui.panel>

    {{-- Folder registry: register, edit inline, enroll a tag from a real scan, archive with supervisor password. --}}
    <x-ui.panel title="Folder registry" heading-id="cabinet-folder-title" id="cabinet-folder-registry" class="scroll-mt-24">
        <x-slot:meta>{{ $files->total() }} {{ \Illuminate\Support\Str::plural('folder', $files->total()) }}</x-slot:meta>
        <x-slot:actions>
            <flux:button size="sm" variant="outline" :href="route('admin.smart-cabinet.index', ['folder_status' => $folderStatus === 'active' ? 'archived' : 'active']).'#cabinet-folder-registry'">{{ $folderStatus === 'active' ? 'View archived folders' : 'View active folders' }}</flux:button>
        </x-slot:actions>
        <div class="space-y-4 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
            @error('rfid_enrollment')
                <flux:callout variant="danger" icon="exclamation-triangle" role="alert"><flux:callout.text>{{ $message }}</flux:callout.text></flux:callout>
            @enderror
            <flux:text class="text-sm">Register a folder, assign it to a drawer, then enroll its UHF tag through the YRM100 reader mapped to that drawer. The tag is saved only after the Raspberry Pi reports a real scan.</flux:text>
            @if ($cabinets->total() > 0)
                @php($creatingFailed = $failedForm === 'folder-create')
                @scopeErrors($creatingFailed ? $allErrors : $noErrors)
                <form class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[1fr_1.4fr_1fr_0.8fr_auto] xl:items-end" method="POST" action="{{ route('admin.smart-cabinet.folders.store') }}" data-folder-create-form>
                    @csrf
                    <input type="hidden" name="_form" value="folder-create">
                    <flux:field>
                        <flux:label>Folder reference</flux:label>
                        <flux:input name="file_reference" required maxlength="100" pattern="[A-Za-z0-9][A-Za-z0-9._\/\-]*" :value="$creatingFailed ? old('file_reference') : ''" placeholder="e.g. DOC-2026-001" autocomplete="off" />
                        <flux:error name="file_reference" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Folder name</flux:label>
                        <flux:input name="file_name" required maxlength="255" :value="$creatingFailed ? old('file_name') : ''" placeholder="Folder name" autocomplete="off" />
                        <flux:error name="file_name" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Cabinet</flux:label>
                        <flux:select name="cabinet_device_id" required>
                            @foreach ($cabinetOptions as $cabinetOption)
                                <flux:select.option :value="$cabinetOption->id" :selected="$creatingFailed && old('cabinet_device_id') == $cabinetOption->id">{{ $cabinetOption->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="cabinet_device_id" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Drawer</flux:label>
                        <flux:select name="drawer_reference" required>
                            <flux:select.option value="drawer_1" :selected="($creatingFailed ? old('drawer_reference', 'drawer_1') : 'drawer_1') === 'drawer_1'">Drawer 1</flux:select.option>
                            <flux:select.option value="drawer_2" :selected="$creatingFailed && old('drawer_reference') === 'drawer_2'">Drawer 2</flux:select.option>
                        </flux:select>
                        <flux:error name="drawer_reference" />
                    </flux:field>
                    <flux:button type="submit" variant="primary" icon="plus">Add folder</flux:button>
                </form>
                @scopeErrors($allErrors)
            @else
                <x-ui.empty-state title="No cabinet available" icon="server-stack">Register a cabinet before adding folders to the registry.</x-ui.empty-state>
            @endif
        </div>

        @if ($files->isEmpty())
            <x-ui.empty-state :title="'No '.$folderStatus.' folders registered'" icon="folder">Registered folder details and confirmed RFID tags will appear here.</x-ui.empty-state>
        @else
            <x-ui.table label="Registered folders" table-class="min-w-[64rem]">
                <thead>
                    <tr>
                        <th scope="col">Reference</th>
                        <th scope="col">Folder name, cabinet &amp; drawer</th>
                        <th scope="col">RFID enrollment</th>
                        <th scope="col">Record status</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($files as $file)
                        @php($editingFailed = $failedForm === 'folder-'.$file->id)
                        @scopeErrors($editingFailed ? $allErrors : $noErrors)
                        <tr data-folder-row="{{ $file->id }}">
                            <td class="whitespace-nowrap font-mono text-xs font-semibold">{{ $file->file_reference }}</td>
                            <td>
                                {{-- Editing in place: name, cabinet and drawer change without leaving the list. --}}
                                <form class="grid gap-2 lg:grid-cols-[minmax(10rem,1.5fr)_minmax(8rem,1fr)_7rem_auto]" method="POST" action="{{ route('admin.smart-cabinet.folders.update', $file) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="_form" value="folder-{{ $file->id }}">
                                    <flux:input size="sm" name="file_name" :aria-label="'Folder name for '.$file->file_reference" required maxlength="255" :value="$editingFailed ? old('file_name') : $file->file_name" />
                                    <flux:select size="sm" name="cabinet_device_id" :aria-label="'Cabinet for '.$file->file_reference" required>
                                        @foreach ($cabinetOptions as $cabinetOption)
                                            <flux:select.option :value="$cabinetOption->id" :selected="($editingFailed ? (int) old('cabinet_device_id') : $file->cabinet_device_id) === $cabinetOption->id">{{ $cabinetOption->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:select size="sm" name="drawer_reference" :aria-label="'Drawer for '.$file->file_reference" required>
                                        <flux:select.option value="drawer_1" :selected="($editingFailed ? old('drawer_reference') : $file->drawer_reference) === 'drawer_1'">Drawer 1</flux:select.option>
                                        <flux:select.option value="drawer_2" :selected="($editingFailed ? old('drawer_reference') : $file->drawer_reference) === 'drawer_2'">Drawer 2</flux:select.option>
                                    </flux:select>
                                    <flux:button size="sm" type="submit" variant="outline">Save</flux:button>
                                    @if ($editingFailed && $allErrors->any())
                                        <p class="text-xs font-medium text-red-600 lg:col-span-4 dark:text-red-400" role="alert">{{ $allErrors->first() }}</p>
                                    @endif
                                </form>
                            </td>
                            <td>
                                <div class="flex flex-col items-start gap-1.5">
                                    @if ($file->isRfidEnrollmentPending())
                                        <x-ui.status-badge tone="warning" icon="signal">Waiting for tag</x-ui.status-badge>
                                        <span class="text-xs text-zinc-600 dark:text-zinc-400">Place ONLY this folder's tag inside Drawer {{ $file->drawer_reference === 'drawer_1' ? '1' : '2' }}.</span>
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">Expires {{ $file->latestEnrollmentRequest->expires_at->format('g:i A') }}</span>
                                        <form method="POST" action="{{ route('admin.smart-cabinet.folders.rfid-cancel', $file) }}">
                                            @csrf
                                            <input type="hidden" name="request_id" value="{{ $file->latestEnrollmentRequest->request_id }}">
                                            <flux:button size="xs" type="submit" variant="outline">Cancel enrollment</flux:button>
                                        </form>
                                    @elseif ($file->isRfidEnrolled())
                                        <x-ui.status-badge tone="success" icon="check-circle">Enrolled</x-ui.status-badge>
                                    @else
                                        <x-ui.status-badge tone="neutral">Not enrolled</x-ui.status-badge>
                                    @endif
                                    @if (! $file->isRfidEnrollmentPending() && $file->latestEnrollmentRequest && $file->latestEnrollmentRequest->status !== 'enrolled')
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">Last enrollment: {{ ucfirst($file->latestEnrollmentRequest->status === 'pending' ? 'expired' : $file->latestEnrollmentRequest->status) }}</span>
                                    @endif
                                    @if ($file->is_active && ! $file->isRfidEnrollmentPending())
                                        <form method="POST" action="{{ route($file->isRfidEnrolled() ? 'admin.smart-cabinet.folders.rfid-replace' : 'admin.smart-cabinet.folders.rfid-enrollment', $file) }}">
                                            @csrf
                                            <flux:button size="xs" type="submit" variant="outline">{{ $file->isRfidEnrolled() ? 'Replace tag' : 'Enroll RFID tag' }}</flux:button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                            <td><x-ui.status-badge :tone="$file->is_active ? 'success' : 'neutral'">{{ $file->is_active ? 'Active' : 'Archived' }}</x-ui.status-badge></td>
                            <td class="text-end">
                                @if ($file->is_active)
                                    <flux:button size="sm" variant="ghost" icon="archive-box" class="text-red-700! hover:bg-red-50! dark:text-red-400! dark:hover:bg-red-400/10!" data-folder-archive data-archive-url="{{ route('admin.smart-cabinet.folders.archive', $file) }}" data-file-reference="{{ $file->file_reference }}" aria-haspopup="dialog">Archive<span class="sr-only">: {{ $file->file_reference }}</span></flux:button>
                                @else
                                    <form method="POST" action="{{ route('admin.smart-cabinet.folders.restore', $file) }}">
                                        @csrf
                                        @method('PATCH')
                                        <flux:button size="sm" type="submit" variant="outline" icon="arrow-uturn-left">Restore</flux:button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @scopeErrors($allErrors)
                </tbody>
            </x-ui.table>
            @if ($files->hasPages())
                <div class="border-t border-zinc-200 px-5 py-3 dark:border-zinc-700"><x-ui.pager :paginator="$files" singular="folder" plural="folders" /></div>
            @endif
        @endif
    </x-ui.panel>

    {{-- Archiving keeps the folder and its history; rfid-file-tracking.js sends the supervisor password and reloads. --}}
    <flux:modal name="cabinet-folder-archive" id="cabinet-folder-archive-dialog" class="w-full max-w-md" data-labelledby="cabinet-folder-archive-title">
        <form class="space-y-4" data-folder-archive-form aria-describedby="cabinet-folder-archive-description">
            <div class="pe-8">
                <flux:heading level="2" size="lg" id="cabinet-folder-archive-title">Archive folder</flux:heading>
                <flux:text class="mt-2" id="cabinet-folder-archive-description">This keeps the folder and its movement history in the records. Confirm with the active Barangay Captain or Super Admin password.</flux:text>
            </div>
            <p class="rounded-lg bg-zinc-100 px-3 py-2 font-mono text-sm font-medium dark:bg-white/10" data-folder-archive-reference></p>
            <flux:field>
                <flux:label>Barangay Captain or Super Admin password</flux:label>
                <flux:input id="cabinet-folder-archive-password" type="password" input:name="super_admin_password" autocomplete="current-password" required>
                    <x-slot name="iconTrailing">
                        <x-password-toggle target="cabinet-folder-archive-password" class="grid size-8 place-items-center rounded-md text-zinc-500 hover:bg-zinc-800/5 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white" />
                    </x-slot>
                </flux:input>
                <p class="text-sm font-medium text-red-600 dark:text-red-400" data-folder-archive-error role="alert" hidden></p>
            </flux:field>
            <div class="flex justify-end gap-2 pt-1">
                <flux:button type="button" variant="ghost" data-folder-archive-cancel>Cancel</flux:button>
                <flux:button type="submit" variant="danger" data-folder-archive-submit>Confirm archive</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
@endsection
