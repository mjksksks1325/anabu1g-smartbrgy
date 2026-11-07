@extends('layouts.admin-iot')
@section('title', 'RFID File Tracking')
@section('staff-ui', 'true')
@section('content')
<div data-ui class="text-zinc-800 dark:text-zinc-100">
    <x-ui.page-header eyebrow="IoT Security / File Movement" title="RFID File Tracking" description="Recorded movements of physical files. Each event shows who removed or returned a file, when, and where. Movements without an identified employee are flagged for review." />

    <flux:callout icon="information-circle" color="sky" class="mb-5">
        <flux:callout.text>RFID scans and cabinet events will appear here after trusted Raspberry Pi integration is connected. No scan can be simulated from this page.</flux:callout.text>
    </flux:callout>

    {{-- rfid-file-tracking.js filters in place: it swaps [data-filter-region] and the [data-filter-count] text. --}}
    <x-ui.panel title="Movement history" heading-id="movement-history-title" data-filter-results aria-busy="false">
        <x-slot:meta><span data-filter-count>{{ $movements->total() }} recorded {{ \Illuminate\Support\Str::plural('event', $movements->total()) }}</span></x-slot:meta>
        <form class="grid grid-cols-2 gap-3 border-b border-zinc-200 px-5 py-4 md:grid-cols-3 xl:grid-cols-[minmax(14rem,2fr)_repeat(4,minmax(8rem,1fr))] dark:border-zinc-700" method="GET" action="{{ route('staff.rfid-files.index') }}" role="search" aria-label="Filter file movements" data-auto-filter>
            <flux:field class="col-span-2 md:col-span-3 xl:col-span-1">
                <flux:label>Search file, RFID tag, or employee</flux:label>
                <flux:input type="search" name="search" autocomplete="off" :value="$filters['search'] ?? ''" icon="magnifying-glass" placeholder="File reference, RFID tag, or employee" />
            </flux:field>
            <flux:field>
                <flux:label>Action</flux:label>
                <flux:select name="action">
                    <flux:select.option value="">All actions</flux:select.option>
                    <flux:select.option value="removed" :selected="($filters['action'] ?? '') === 'removed'">Removed</flux:select.option>
                    <flux:select.option value="returned" :selected="($filters['action'] ?? '') === 'returned'">Returned</flux:select.option>
                </flux:select>
            </flux:field>
            <flux:field>
                <flux:label>Date</flux:label>
                <flux:input type="date" name="date" :value="$filters['date'] ?? ''" />
            </flux:field>
            <flux:field>
                <flux:label>Cabinet</flux:label>
                <flux:select name="cabinet">
                    <flux:select.option value="">All cabinets</flux:select.option>
                    @foreach ($cabinets as $cabinet)
                        <flux:select.option :value="$cabinet->id" :selected="($filters['cabinet'] ?? null) == $cabinet->id">{{ $cabinet->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
            <flux:field>
                <flux:label>Drawer</flux:label>
                <flux:input type="search" name="drawer" autocomplete="off" :value="$filters['drawer'] ?? ''" placeholder="Drawer ID" />
            </flux:field>
        </form>

        <div data-filter-region>
            @if ($movements->isEmpty())
                @if (array_filter($filters))
                    <x-ui.empty-state title="No movements match these filters" icon="magnifying-glass">Try another file, tag or employee, or clear a filter.</x-ui.empty-state>
                @else
                    <x-ui.empty-state title="No file movements recorded" icon="signal">Events will show here when a connected device reports a verified removal or return.</x-ui.empty-state>
                @endif
            @else
                <x-ui.table label="File movements" table-class="xl:min-w-[52rem]">
                    <thead>
                        <tr>
                            <th scope="col">When</th>
                            <th scope="col">File</th>
                            <th scope="col">Action</th>
                            <th scope="col" class="hidden lg:table-cell">Current status</th>
                            <th scope="col">Employee</th>
                            <th scope="col" class="hidden md:table-cell">Cabinet / drawer</th>
                            <th scope="col" class="hidden xl:table-cell">RFID tag</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($movements as $movement)
                            <tr id="movement-{{ $movement->id }}" class="scroll-mt-24 target:[&>td]:bg-amber-50 dark:target:[&>td]:bg-amber-400/10">
                                <td class="whitespace-nowrap tabular-nums"><time datetime="{{ $movement->occurred_at->toIso8601String() }}">{{ $movement->occurred_at->format('M j, Y') }}<span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $movement->occurred_at->format('g:i A') }}</span></time></td>
                                <td><span class="block font-medium text-zinc-800 dark:text-white" data-no-admin-translate>{{ $movement->file_name ?: $movement->file_reference }}</span><span class="block font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ $movement->file_reference }}</span></td>
                                <td><x-ui.status-badge :tone="$movement->action === 'removed' ? 'warning' : 'success'">{{ ucfirst($movement->action) }}</x-ui.status-badge></td>
                                <td class="hidden lg:table-cell"><x-ui.status-badge :tone="$movement->current_action === 'returned' ? 'success' : ($movement->current_action === 'removed' ? 'warning' : 'neutral')">{{ $movement->current_action === 'returned' ? 'In cabinet' : ($movement->current_action === 'removed' ? 'Checked out' : 'Unknown') }}</x-ui.status-badge></td>
                                <td>
                                    @if ($movement->user_id === null)
                                        <x-ui.status-badge tone="danger" icon="exclamation-triangle">Folder moved with no cabinet access</x-ui.status-badge>
                                    @else
                                        <span data-no-admin-translate>{{ $movement->user?->name ?? 'Unknown employee' }}</span>
                                    @endif
                                </td>
                                <td class="hidden md:table-cell">{{ $movement->cabinetDevice?->name ?? 'Not recorded' }}<span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $movement->drawer_reference ?? 'Drawer not recorded' }}</span></td>
                                <td class="hidden xl:table-cell"><code class="font-mono text-xs">{{ $movement->rfid_tag ?? 'Not recorded' }}</code></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
                @if ($movements->hasPages())
                    <div class="border-t border-zinc-200 px-5 py-3 dark:border-zinc-700">
                        <x-ui.pager :paginator="$movements" singular="event" plural="events" />
                    </div>
                @endif
            @endif
        </div>
    </x-ui.panel>
</div>
@endsection
