@extends('layouts.admin-iot')
@section('title', 'Document Request Details')
@section('staff-ui', 'true')
@section('content')
<div data-ui class="text-zinc-800 dark:text-zinc-100 document-request-detail">
    <x-ui.page-header eyebrow="Records / Document Requests" title="Document request details" description="Review and manage the resident's document request.">
        <x-slot:actions>
            <flux:button variant="outline" icon="arrow-left" :href="route('staff.document-requests.list')">All requests</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle" class="mb-5" role="status">
            <flux:callout.text>{{ session('success') }}</flux:callout.text>
        </flux:callout>
    @endif

    @if ($errors->any())
        <flux:callout variant="danger" icon="exclamation-triangle" class="mb-5" role="alert">
            <flux:callout.heading>Unable to update request</flux:callout.heading>
            <flux:callout.text>
                <ul class="list-disc ps-5">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_24rem]">
        <x-ui.panel title="Request information" heading-id="request-information-title">
            <x-slot:meta><x-staff.request-status :status="$documentRequest->status" /></x-slot:meta>
            <div class="px-5 py-5">
                <x-ui.details>
                    <x-ui.detail label="Reference code" class="font-mono">{{ $documentRequest->reference_code }}</x-ui.detail>
                    <x-ui.detail label="Resident name" data-no-admin-translate>{{ $documentRequest->full_name }}</x-ui.detail>
                    <x-ui.detail label="Document type">{{ $documentRequest->document_type }}</x-ui.detail>
                    <x-ui.detail label="Date submitted" class="tabular-nums">{{ $documentRequest->created_at->format('M d, Y h:i A') }}</x-ui.detail>
                    <x-ui.detail label="Date of birth">{{ $documentRequest->date_of_birth ?? 'Not provided' }}</x-ui.detail>
                    <x-ui.detail label="Email address" data-no-admin-translate>{{ $documentRequest->email ?? 'Not provided' }}</x-ui.detail>
                    <x-ui.detail label="Address" wide data-no-admin-translate>{{ $documentRequest->address }}</x-ui.detail>
                    <x-ui.detail label="Purpose" wide data-no-admin-translate>{{ $documentRequest->purpose ?? 'Not provided' }}</x-ui.detail>
                    @if ($documentRequest->business_name)
                        <x-ui.detail label="Business name" wide data-no-admin-translate>{{ $documentRequest->business_name }}</x-ui.detail>
                    @endif
                    @if ($documentRequest->status === 'rejected' && $documentRequest->rejection_reason)
                        <x-ui.detail label="Reason for rejection" wide data-no-admin-translate>{{ $documentRequest->rejection_reason }}</x-ui.detail>
                    @endif
                </x-ui.details>
            </div>
        </x-ui.panel>

        @can('documents.process')
            <x-ui.panel title="Request status" description="Update the processing status and staff remarks." heading-id="request-status-title">
                <form class="space-y-4 px-5 py-5" method="POST" action="{{ route('staff.document-requests.update-status', $documentRequest) }}" data-request-status-form>
                    @csrf
                    @method('PATCH')
                    <flux:field>
                        <flux:label>Status</flux:label>
                        <flux:select name="status" required>
                            @foreach (['pending' => 'Pending', 'processing' => 'Processing', 'approved' => 'Approved', 'ready_for_release' => 'Ready for Release', 'rejected' => 'Rejected'] as $value => $label)
                                <flux:select.option :value="$value" :selected="old('status', $documentRequest->status) === $value">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="status" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Remarks</flux:label>
                        <flux:textarea name="remarks" rows="4" maxlength="1000" placeholder="Add optional staff remarks...">{{ old('remarks', $documentRequest->remarks) }}</flux:textarea>
                        <flux:error name="remarks" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Reason for rejection (required when rejecting)</flux:label>
                        <flux:textarea id="rejection_reason" name="rejection_reason" rows="3" maxlength="1000">{{ old('rejection_reason', $documentRequest->rejection_reason) }}</flux:textarea>
                        <flux:description>Provide at least 10 characters explaining the decision.</flux:description>
                        <flux:error name="rejection_reason" />
                    </flux:field>
                    <div class="flex flex-wrap justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700 document-request-actions">
                        <flux:button variant="ghost" :href="route(App\StaffPermissions::landing(auth()->user()))">Cancel</flux:button>
                        <flux:button type="submit" variant="primary">Update Request</flux:button>
                    </div>
                </form>
            </x-ui.panel>
        @endcan
    </div>
</div>
@endsection
