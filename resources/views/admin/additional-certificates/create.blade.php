@extends('layouts.admin-iot')
@section('title', $type->value)
@section('staff-ui', 'true')
@section('content')
@php
    $detailValue = function (string $name) {
        $value = old('details.'.$name);

        return is_array($value) ? implode("\n", $value) : $value;
    };
@endphp
<div data-ui class="text-zinc-800 dark:text-zinc-100">
    <x-ui.page-header eyebrow="Records / Other official certificates" :title="$type->value" description="Find the resident, complete the details the certificate states, then preview it.">
        <x-slot:actions>
            <flux:button variant="outline" icon="arrow-left" :href="route('staff.additional-certificates.index')">All certificates</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    @unless ($type->issuanceEnabled())
        <flux:callout icon="exclamation-triangle" color="amber" class="mb-6" data-issuance-off>
            <flux:callout.heading>Issuing is off until the barangay approves this certificate</flux:callout.heading>
            <flux:callout.text>Still to be approved: {{ implode('; ', $type->pendingApprovals()) }}. You can complete and preview it; the preview cannot be printed.</flux:callout.text>
        </flux:callout>
    @endunless

    @error('certificate')
        <flux:callout icon="x-circle" color="red" class="mb-6" role="alert">
            <flux:callout.heading>The certificate cannot be prepared yet</flux:callout.heading>
            <flux:callout.text>
                <ul class="list-disc ps-4">
                    @foreach ($errors->get('certificate') as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </flux:callout.text>
        </flux:callout>
    @enderror

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
        <x-ui.panel title="1. Find the resident" heading-id="resident-search-title" description="By name or resident number. Only active records are listed.">
            <form method="GET" action="{{ route('staff.additional-certificates.create', $type->slug()) }}" class="space-y-4 p-5" role="search">
                <flux:field>
                    <flux:label>{{ $type->needsPartner() ? 'First resident' : 'Resident' }}</flux:label>
                    <flux:input type="search" name="resident_search" :value="$residentSearch" maxlength="100" icon="magnifying-glass" />
                </flux:field>
                @if ($type->needsPartner())
                    <flux:field>
                        <flux:label>Partner</flux:label>
                        <flux:input type="search" name="partner_search" :value="$partnerSearch" maxlength="100" icon="magnifying-glass" />
                    </flux:field>
                @endif
                <flux:button type="submit" variant="outline">Search</flux:button>
            </form>
            @if ($residentSearch !== '' && $type->acceptsEvidence())
                <ul class="divide-y divide-zinc-200 border-t border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700" aria-label="Matching residents" data-resident-results>
                    @forelse ($residents as $resident)
                        <li class="flex items-center justify-between gap-3 px-5 py-3">
                            <span class="min-w-0">
                                <span class="block truncate font-medium" data-no-admin-translate>{{ $resident->full_name }}</span>
                                <span class="block font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ $resident->resident_number }}</span>
                            </span>
                            <flux:button size="sm" :variant="$chosenResident?->is($resident) ? 'primary' : 'outline'" :href="route('staff.additional-certificates.create', [$type->slug(), 'resident_id' => $resident->id, 'resident_search' => $residentSearch])">{{ $chosenResident?->is($resident) ? 'Chosen' : 'Choose' }}<span class="sr-only">: {{ $resident->full_name }}</span></flux:button>
                        </li>
                    @empty
                        <li class="px-5 py-3 text-sm text-zinc-500 dark:text-zinc-400">No active resident matches this search.</li>
                    @endforelse
                </ul>
            @endif
        </x-ui.panel>

        <x-ui.panel title="2. Certificate details" heading-id="certificate-details-title" :description="'Layout and wording: '.$type->officialReference()">
            <form method="POST" action="{{ route('staff.additional-certificates.preview', $type->slug()) }}" class="space-y-5 p-5" data-additional-certificate-form>
                @csrf
                <flux:field>
                    <flux:label>{{ $type->needsPartner() ? 'First resident' : 'Resident' }}</flux:label>
                    <flux:select name="resident_id" required>
                        <flux:select.option value="">{{ $residents->isEmpty() ? 'Search for the resident first' : 'Choose the resident' }}</flux:select.option>
                        @foreach ($residents as $resident)
                            <flux:select.option :value="$resident->id" :selected="(string) old('resident_id', $chosenResident?->id) === (string) $resident->id">{{ $resident->full_name }} · {{ $resident->resident_number }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="resident_id" />
                </flux:field>
                @if ($type->needsPartner())
                    <flux:field>
                        <flux:label>Partner</flux:label>
                        <flux:select name="partner_resident_id" required>
                            <flux:select.option value="">{{ $partners->isEmpty() ? 'Search for the partner first' : 'Choose the partner' }}</flux:select.option>
                            @foreach ($partners as $partner)
                                <flux:select.option :value="$partner->id" :selected="(string) old('partner_resident_id') === (string) $partner->id">{{ $partner->full_name }} · {{ $partner->resident_number }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="partner_resident_id" />
                    </flux:field>
                @endif

                @foreach ($type->fields() as $name => $field)
                    @continue($field['type'] === 'hidden')
                    <flux:field>
                        <flux:label>{{ $field['label'] }}</flux:label>
                        @if ($field['type'] === 'lines')
                            <flux:textarea :name="'details['.$name.']'" rows="4">{{ $detailValue($name) }}</flux:textarea>
                        @else
                            <flux:input :type="$field['type']" :name="'details['.$name.']'" :value="$detailValue($name)" :step="$field['type'] === 'number' ? 'any' : null" :max="$field['type'] === 'date' ? now()->toDateString() : null" />
                        @endif
                        @isset($field['help'])
                            <flux:description>{{ $field['help'] }}</flux:description>
                        @endisset
                        <flux:error :name="'details.'.$name" />
                        @if ($field['type'] === 'lines')
                            <flux:error :name="'details.'.$name.'.*'" />
                        @endif
                    </flux:field>
                @endforeach

                <div class="flex flex-wrap justify-end gap-2 border-t border-zinc-200 pt-5 dark:border-zinc-700">
                    <flux:button type="submit" variant="outline" icon="eye">Preview certificate</flux:button>
                    <flux:button type="submit" variant="primary" :formaction="route('staff.additional-certificates.store', $type->slug())" :disabled="! $type->issuanceEnabled()" data-issue-additional-certificate>Issue and print</flux:button>
                </div>
            </form>
        </x-ui.panel>
    </div>

    @if ($type->acceptsEvidence())
        <x-ui.panel title="3. Community service photographs" heading-id="evidence-title" class="mt-6"
            :description="'Printed on page 2 of the certificate, uncropped. Up to '.\App\Models\CertificateEvidence::MAX_PER_CERTIFICATE.' photographs; JPEG, PNG or WebP, at least 400 × 400 pixels, up to 8 MB each. Files are kept private.'">
            @if ($chosenResident === null)
                <x-ui.empty-state title="Choose the resident first" icon="photo">Search for the resident in step 1 and choose them; their photographs appear here.</x-ui.empty-state>
            @else
                <div class="space-y-5 p-5" data-evidence-panel>
                    <p class="text-sm">For <strong data-no-admin-translate>{{ $chosenResident->full_name }}</strong>: {{ $evidence->count() }} of {{ \App\Models\CertificateEvidence::MAX_PER_CERTIFICATE }} photographs.</p>
                    @if (session('status'))
                        <flux:callout icon="check-circle" color="green" role="status"><flux:callout.text>{{ session('status') }}</flux:callout.text></flux:callout>
                    @endif
                    @if ($evidence->isNotEmpty())
                        <ul class="grid grid-cols-2 gap-4 sm:grid-cols-4" aria-label="Uploaded photographs">
                            @foreach ($evidence as $index => $photograph)
                                <li class="space-y-2" data-evidence="{{ $photograph->uuid }}">
                                    <img class="aspect-[3/4] w-full rounded-lg border border-zinc-200 bg-zinc-50 object-contain dark:border-zinc-700 dark:bg-zinc-900" src="{{ route('staff.certificate-evidence.show', $photograph) }}" alt="Community service photograph {{ $index + 1 }}">
                                    <form method="POST" action="{{ route('staff.certificate-evidence.destroy', $photograph) }}" onsubmit="return confirm('Remove this photograph? It will be deleted.')">
                                        @csrf
                                        @method('DELETE')
                                        <flux:button type="submit" size="sm" variant="ghost" icon="trash" class="w-full">Remove<span class="sr-only"> photograph {{ $index + 1 }}</span></flux:button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($evidence->count() < \App\Models\CertificateEvidence::MAX_PER_CERTIFICATE)
                        <form method="POST" enctype="multipart/form-data" action="{{ route('staff.additional-certificates.evidence.store', [$type->slug(), $chosenResident]) }}" class="flex flex-col gap-3 sm:flex-row sm:items-end" data-evidence-upload>
                            @csrf
                            <flux:field class="flex-1">
                                <flux:label>Add photographs</flux:label>
                                <flux:input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" required />
                                <flux:error name="photos" />
                                <flux:error name="photos.*" />
                            </flux:field>
                            <flux:button type="submit" variant="outline" icon="arrow-up-tray">Upload</flux:button>
                        </form>
                    @else
                        <flux:error name="photos" />
                    @endif
                </div>
            @endif
        </x-ui.panel>
    @endif
</div>
@endsection
