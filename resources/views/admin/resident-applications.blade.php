@extends('layouts.admin-iot')
@section('title', 'Resident Records')
@section('staff-ui', 'true')
@section('content')
@php
    $allErrors = $errors;
    $noErrors = new \Illuminate\Support\ViewErrorBag;
    $statusLabels = ['pending' => 'Pending review', 'needs_correction' => 'Needs correction', 'approved' => 'Approved', 'declined' => 'Declined'];
    $statusTones = ['pending' => 'warning', 'needs_correction' => 'info', 'approved' => 'success', 'declined' => 'danger'];
    $filters = [
        'all' => ['label' => 'All', 'hint' => 'Every application', 'icon' => 'inbox-stack', 'tone' => 'brand', 'count' => array_sum($statusCounts)],
        'pending' => ['label' => 'Pending review', 'hint' => 'Waiting for staff', 'icon' => 'clock', 'tone' => 'warning', 'count' => $statusCounts['pending'] ?? 0],
        'needs_correction' => ['label' => 'Needs correction', 'hint' => 'Back with the applicant', 'icon' => 'arrow-uturn-left', 'tone' => 'info', 'count' => $statusCounts['needs_correction'] ?? 0],
        'approved' => ['label' => 'Approved', 'hint' => 'Resident record created', 'icon' => 'check-circle', 'tone' => 'success', 'count' => $statusCounts['approved'] ?? 0],
        'declined' => ['label' => 'Declined', 'hint' => 'Closed with a reason', 'icon' => 'x-circle', 'tone' => 'danger', 'count' => $statusCounts['declined'] ?? 0],
    ];
@endphp
<div data-ui class="text-zinc-800 dark:text-zinc-100">
    <x-ui.page-header eyebrow="Records" title="New resident applications" description="Review the submitted details before approving resident registration. Portal account registration follows barangay approval.">
        <x-slot:actions>
            <flux:button variant="outline" icon="arrow-left" :href="route('staff.residents.index')" wire:navigate.hover>Back to Resident Records</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    <nav class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5" aria-label="Filter applications by status" data-application-filters>
        @foreach ($filters as $filter => $card)
            <x-ui.kpi-card :label="$card['label']" :value="number_format($card['count'])" :hint="$card['hint']" :icon="$card['icon']" :tone="$card['tone']"
                :href="route('staff.resident-applications.index', array_filter(['status' => $filter, 'search' => $search]))" :current="$status === $filter" />
        @endforeach
    </nav>

    <x-ui.panel title="Applications" heading-id="applications-title">
        <x-slot:meta>{{ number_format($applications->total()) }} {{ $applications->total() === 1 ? 'application' : 'applications' }}</x-slot:meta>
        <form class="flex flex-col gap-2 border-b border-zinc-200 px-5 py-3 sm:flex-row dark:border-zinc-700" method="GET" action="{{ route('staff.resident-applications.index') }}" role="search" aria-label="Search applications">
            @if ($status !== 'all')<input type="hidden" name="status" value="{{ $status }}">@endif
            <flux:input type="search" name="search" :value="$search" icon="magnifying-glass" placeholder="Name, reference or email" aria-label="Search by name, reference or email" maxlength="100" class="sm:max-w-sm" />
            <div class="flex gap-2">
                <flux:button type="submit" variant="outline">Search</flux:button>
                @if ($search !== '')
                    <flux:button variant="ghost" :href="route('staff.resident-applications.index', array_filter(['status' => $status === 'all' ? null : $status]))">Clear</flux:button>
                @endif
            </div>
        </form>

        @if ($applications->isEmpty())
            <x-ui.empty-state :title="$search !== '' || $status !== 'all' ? 'No applications match these filters' : 'No registration applications found.'" icon="user-group">
                {{ $search !== '' || $status !== 'all' ? 'Try another name, reference or email, or choose another status.' : 'New submissions will appear here for authorized review.' }}
            </x-ui.empty-state>
        @else
            <x-ui.table label="Resident applications">
                <thead>
                    <tr>
                        <th scope="col">Applicant</th>
                        <th scope="col" class="hidden md:table-cell">Reference</th>
                        <th scope="col" class="hidden lg:table-cell">Submitted</th>
                        <th scope="col" class="hidden sm:table-cell">Status</th>
                        <th scope="col" class="text-end"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($applications as $application)
                        @php($applicantName = App\Models\ResidentRegistrationApplication::fullName($application->details))
                        <tr id="resident-application-{{ $application->id }}" data-application-row>
                            <td>
                                <span class="block font-medium text-zinc-800 dark:text-white" data-no-admin-translate>{{ $applicantName }}</span>
                                <span class="block text-xs text-zinc-500 dark:text-zinc-400" data-no-admin-translate>{{ $application->email }}</span>
                                <span class="mt-0.5 block font-mono text-xs text-zinc-500 md:hidden dark:text-zinc-400">{{ $application->reference }}</span>
                                <span class="mt-1 block sm:hidden"><x-ui.status-badge :tone="$statusTones[$application->status] ?? 'neutral'">{{ $statusLabels[$application->status] ?? ucfirst(str_replace('_', ' ', $application->status)) }}</x-ui.status-badge></span>
                            </td>
                            <td class="hidden font-mono text-xs md:table-cell">{{ $application->reference }}</td>
                            <td class="hidden whitespace-nowrap tabular-nums lg:table-cell">{{ $application->created_at->format('M d, Y h:i A') }}</td>
                            <td class="hidden sm:table-cell"><x-ui.status-badge :tone="$statusTones[$application->status] ?? 'neutral'" data-application-state="{{ $application->status }}">{{ $statusLabels[$application->status] ?? ucfirst(str_replace('_', ' ', $application->status)) }}</x-ui.status-badge></td>
                            <td class="text-end">
                                <flux:modal.trigger name="application-{{ $application->id }}">
                                    <flux:button size="sm" :variant="$application->status === 'pending' ? 'primary' : 'outline'" aria-haspopup="dialog">{{ $application->status === 'pending' ? 'Review' : 'View' }}<span class="sr-only">: {{ $applicantName }}</span></flux:button>
                                </flux:modal.trigger>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif

        @if ($applications->hasPages())
            <div class="border-t border-zinc-200 px-5 py-3 dark:border-zinc-700">
                <x-ui.pager :paginator="$applications" singular="application" plural="applications" />
            </div>
        @endif
    </x-ui.panel>

    {{-- Each application opens in a side panel, so the list stays in view while staff check the photo and details. --}}
    @foreach ($applications as $application)
        @php($applicantName = App\Models\ResidentRegistrationApplication::fullName($application->details))
        @php($failedHere = (string) old('_application') === (string) $application->id)
        @scopeErrors($failedHere ? $allErrors : $noErrors)
        <flux:modal flyout name="application-{{ $application->id }}" class="w-full md:w-[38rem]" data-application-dialog="{{ $application->id }}" data-labelledby="application-{{ $application->id }}-title"
            :data-open-on-load="($failedHere && $allErrors->any()) || request()->integer('application') === $application->id ? '' : null">
            <div class="space-y-6">
                <div class="pe-8">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:heading level="2" size="lg" id="application-{{ $application->id }}-title" data-no-admin-translate>{{ $applicantName }}</flux:heading>
                        <x-ui.status-badge :tone="$statusTones[$application->status] ?? 'neutral'">{{ $statusLabels[$application->status] ?? ucfirst(str_replace('_', ' ', $application->status)) }}</x-ui.status-badge>
                    </div>
                    <flux:text class="mt-1 text-sm"><span class="font-mono">{{ $application->reference }}</span> <span aria-hidden="true">&middot;</span> <span>Submitted {{ $application->created_at->format('M d, Y h:i A') }}</span></flux:text>
                </div>

                <div class="flex flex-col gap-5 sm:flex-row">
                    <figure class="shrink-0">
                        <img class="h-[12.5rem] w-40 rounded-lg border border-zinc-200 bg-zinc-100 object-cover dark:border-zinc-700 dark:bg-zinc-900" src="{{ route('staff.resident-applications.photo', $application) }}" alt="Applicant resident photo" width="160" height="200" loading="lazy">
                        <figcaption class="mt-1.5 text-xs text-zinc-500 dark:text-zinc-400">Resident photo</figcaption>
                    </figure>
                    <x-ui.details class="flex-1">
                        @foreach ($application->details as $field => $value)
                            <x-ui.detail :label="ucfirst(str_replace('_', ' ', $field))" :wide="$field === 'address'" data-no-admin-translate>{{ $value ?: 'Not provided' }}</x-ui.detail>
                        @endforeach
                        <x-ui.detail label="Email address" wide data-no-admin-translate>{{ $application->email }}</x-ui.detail>
                    </x-ui.details>
                </div>

                @if ($application->remarks || $application->reviewed_at)
                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-white/5">
                        @if ($application->remarks)
                            <flux:heading level="3">Review remarks</flux:heading>
                            <p class="mt-1 text-sm text-zinc-700 dark:text-zinc-300" data-no-admin-translate>{{ $application->remarks }}</p>
                        @endif
                        @if ($application->reviewed_at)
                            <flux:text class="mt-2 text-xs">Reviewed {{ $application->reviewed_at->format('M d, Y h:i A') }} &middot; Staff #{{ $application->reviewed_by }}</flux:text>
                        @endif
                    </div>
                @endif

                @if ($application->status === 'pending')
                    <form class="space-y-4 border-t border-zinc-200 pt-5 dark:border-zinc-700" method="POST" action="{{ route('staff.resident-applications.update', $application) }}" data-application-review-form>
                        @csrf
                        <input type="hidden" name="_application" value="{{ $application->id }}">
                        <flux:heading level="3">Review decision</flux:heading>
                        <flux:field>
                            <flux:label>Decision</flux:label>
                            <flux:select name="decision" required>
                                <flux:select.option value="" :selected="! $failedHere || old('decision') === null">Select a decision</flux:select.option>
                                <flux:select.option value="approved" :selected="$failedHere && old('decision') === 'approved'">Approve resident registration</flux:select.option>
                                <flux:select.option value="needs_correction" :selected="$failedHere && old('decision') === 'needs_correction'">Request correction</flux:select.option>
                                <flux:select.option value="declined" :selected="$failedHere && old('decision') === 'declined'">Decline with reason</flux:select.option>
                            </flux:select>
                            <flux:error name="decision" />
                        </flux:field>
                        <flux:field>
                            <flux:label>Remarks</flux:label>
                            <flux:textarea name="remarks" maxlength="1000" rows="4" aria-describedby="review-remarks-help-{{ $application->id }}">{{ $failedHere ? old('remarks') : '' }}</flux:textarea>
                            <flux:description id="review-remarks-help-{{ $application->id }}">Required when requesting correction or declining an application.</flux:description>
                            <flux:error name="remarks" />
                        </flux:field>
                        <div class="flex justify-end gap-2">
                            <flux:modal.close>
                                <flux:button type="button" variant="ghost">Cancel</flux:button>
                            </flux:modal.close>
                            <flux:button type="submit" variant="primary">Save review</flux:button>
                        </div>
                    </form>
                @elseif ($application->status === 'approved')
                    <flux:callout variant="success" icon="check-circle">
                        <flux:callout.text>Resident record created. Approval does not send an activation code. The resident must continue portal account registration.</flux:callout.text>
                    </flux:callout>
                @endif
            </div>
        </flux:modal>
    @endforeach
    @scopeErrors($allErrors)
</div>
@endsection
