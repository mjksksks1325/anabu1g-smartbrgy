@extends('layouts.admin-iot')
@section('title', 'Other official certificates')
@section('staff-ui', 'true')
@section('content')
<div data-ui class="text-zinc-800 dark:text-zinc-100">
    <x-ui.page-header eyebrow="Records / Certificates" title="Other official certificates" description="Further Barangay Anabu I-G certificates, prepared by staff only. Residents cannot request them online.">
        <x-slot:actions>
            <flux:button variant="outline" icon="squares-2x2" :href="route('staff.document-requests.index')">Certificates board</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    <flux:callout icon="information-circle" class="mb-6">
        <flux:callout.text>Each certificate follows the barangay's own Word template. You can complete and preview any of them now. Issuing stays off for a certificate until the barangay approves its fee and the requirements listed for it.</flux:callout.text>
    </flux:callout>

    <x-ui.panel title="Certificates" heading-id="additional-certificates-title">
        <x-slot:meta>{{ count($types) }} certificates</x-slot:meta>
        <x-ui.table label="Other official certificates">
            <thead>
                <tr>
                    <th scope="col">Certificate</th>
                    <th scope="col" class="hidden lg:table-cell">Still to be approved</th>
                    <th scope="col" class="hidden sm:table-cell">Issuing</th>
                    <th scope="col" class="text-end"><span class="sr-only">Action</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($types as $type)
                    <tr data-additional-certificate="{{ $type->slug() }}">
                        <td>
                            <span class="block font-medium text-zinc-800 dark:text-white">{{ $type->value }}</span>
                            <span class="block text-xs text-zinc-500 dark:text-zinc-400" data-no-admin-translate>Template: {{ $type->officialReference() }}</span>
                            <span class="mt-1 block sm:hidden">
                                @if ($type->issuanceEnabled())
                                    <x-ui.status-badge tone="success">On · {{ $type->fee() === 0 ? 'Free' : 'PHP '.number_format($type->fee(), 2) }}</x-ui.status-badge>
                                @else
                                    <x-ui.status-badge tone="warning">Off until approved</x-ui.status-badge>
                                @endif
                            </span>
                        </td>
                        <td class="hidden lg:table-cell">
                            <ul class="list-disc space-y-1 ps-4 text-sm">
                                @foreach ($type->pendingApprovals() as $pending)
                                    <li>{{ $pending }}</li>
                                @endforeach
                            </ul>
                        </td>
                        <td class="hidden sm:table-cell">
                            @if ($type->issuanceEnabled())
                                <x-ui.status-badge tone="success">On · {{ $type->fee() === 0 ? 'Free' : 'PHP '.number_format($type->fee(), 2) }}</x-ui.status-badge>
                            @else
                                <x-ui.status-badge tone="warning">Off until approved</x-ui.status-badge>
                            @endif
                        </td>
                        <td class="text-end">
                            <flux:button size="sm" variant="outline" :href="route('staff.additional-certificates.create', $type->slug())">Prepare<span class="sr-only">: {{ $type->value }}</span></flux:button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>
    </x-ui.panel>
</div>
@endsection
