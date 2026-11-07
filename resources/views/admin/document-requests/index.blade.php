@extends('layouts.admin-iot')
@section('title', 'Document Requests')
@section('staff-ui', 'true')
@section('content')
@php
    $lanes = [
        null => ['label' => 'All requests', 'hint' => 'Including rejected', 'icon' => 'inbox-stack', 'tone' => 'brand', 'count' => $totalRequests],
        'online' => ['label' => 'New online', 'hint' => 'From the resident portal', 'icon' => 'globe-alt', 'tone' => 'info', 'count' => $laneCounts['online'] ?? 0],
        'processing' => ['label' => 'In progress', 'hint' => 'Being processed', 'icon' => 'clock', 'tone' => 'warning', 'count' => $laneCounts['processing'] ?? 0],
        'ready' => ['label' => 'Ready for release', 'hint' => 'Approved or ready', 'icon' => 'document-check', 'tone' => 'success', 'count' => $laneCounts['ready'] ?? 0],
        'completed' => ['label' => 'Released', 'hint' => 'Completed', 'icon' => 'check-circle', 'tone' => 'neutral', 'count' => $laneCounts['completed'] ?? 0],
    ];
@endphp
<div data-ui class="text-zinc-800 dark:text-zinc-100">
    <x-ui.page-header eyebrow="Records / Document Requests" title="Document Requests" description="Review resident submissions and follow each request through to release.">
        @can('documents.view')
            <x-slot:actions>
                <flux:button variant="outline" icon="squares-2x2" :href="route('staff.document-requests.index')">Certificates board</flux:button>
            </x-slot:actions>
        @endcan
    </x-ui.page-header>

    <nav class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5" aria-label="Filter requests by stage" data-request-filters>
        @foreach ($lanes as $key => $card)
            <x-ui.kpi-card :label="$card['label']" :value="number_format($card['count'])" :hint="$card['hint']" :icon="$card['icon']" :tone="$card['tone']"
                :href="route('staff.document-requests.list', array_filter(['lane' => $key ?: null, 'search' => $search]))" :current="$lane === ($key ?: null)" />
        @endforeach
    </nav>

    <x-ui.panel title="Requests" heading-id="requests-title">
        <x-slot:meta>{{ number_format($requests->total()) }} {{ $requests->total() === 1 ? 'request' : 'requests' }}</x-slot:meta>
        <form class="flex flex-col gap-2 border-b border-zinc-200 px-5 py-3 sm:flex-row dark:border-zinc-700" method="GET" action="{{ route('staff.document-requests.list') }}" role="search" aria-label="Search requests">
            @if ($lane)<input type="hidden" name="lane" value="{{ $lane }}">@endif
            <flux:input type="search" name="search" :value="$search" icon="magnifying-glass" placeholder="Name, reference code or document" aria-label="Search by name, reference code or document" maxlength="100" class="sm:max-w-sm" />
            <div class="flex gap-2">
                <flux:button type="submit" variant="outline">Search</flux:button>
                @if ($search !== '')
                    <flux:button variant="ghost" :href="route('staff.document-requests.list', array_filter(['lane' => $lane]))">Clear</flux:button>
                @endif
            </div>
        </form>

        @if ($requests->isEmpty())
            <x-ui.empty-state :title="$search !== '' || $lane ? 'No requests match these filters' : 'No document requests found.'" icon="document-text">
                {{ $search !== '' || $lane ? 'Try another name, reference code or document, or choose another stage.' : 'Requests from the resident portal and walk-ins will appear here.' }}
            </x-ui.empty-state>
        @else
            <x-ui.table label="Document requests">
                <thead>
                    <tr>
                        <th scope="col" class="hidden sm:table-cell">Reference code</th>
                        <th scope="col">Resident</th>
                        <th scope="col" class="hidden md:table-cell">Document</th>
                        <th scope="col" class="hidden sm:table-cell">Status</th>
                        <th scope="col" class="hidden lg:table-cell">Date submitted</th>
                        <th scope="col" class="text-end"><span class="sr-only">Action</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requests as $request)
                        <tr data-request-row>
                            <td class="hidden whitespace-nowrap font-mono text-xs sm:table-cell">{{ $request->reference_code }}</td>
                            <td>
                                <span class="block font-medium text-zinc-800 dark:text-white" data-no-admin-translate>{{ $request->full_name }}</span>
                                <span class="block font-mono text-xs text-zinc-500 sm:hidden dark:text-zinc-400">{{ $request->reference_code }}</span>
                                <span class="block text-xs text-zinc-500 md:hidden dark:text-zinc-400">{{ $request->document_type }}</span>
                                <span class="mt-1 block sm:hidden"><x-staff.request-status :status="$request->status" /></span>
                            </td>
                            <td class="hidden md:table-cell">{{ $request->document_type }}</td>
                            <td class="hidden sm:table-cell"><x-staff.request-status :status="$request->status" /></td>
                            <td class="hidden whitespace-nowrap tabular-nums lg:table-cell">{{ $request->created_at->format('M d, Y h:i A') }}</td>
                            <td class="text-end">
                                <flux:button size="sm" variant="outline" :href="route('staff.document-requests.show', $request)">View<span class="sr-only">: {{ $request->reference_code }}</span></flux:button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif

        @if ($requests->hasPages())
            <div class="border-t border-zinc-200 px-5 py-3 dark:border-zinc-700">
                <x-ui.pager :paginator="$requests" singular="request" plural="requests" />
            </div>
        @endif
    </x-ui.panel>
</div>
@endsection
