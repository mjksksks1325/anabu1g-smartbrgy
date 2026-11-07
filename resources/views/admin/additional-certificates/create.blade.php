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
        </x-ui.panel>

        <x-ui.panel title="2. Certificate details" heading-id="certificate-details-title" :description="'Layout and wording: '.$type->officialReference()">
            <form method="POST" action="{{ route('staff.additional-certificates.preview', $type->slug()) }}" class="space-y-5 p-5" data-additional-certificate-form>
                @csrf
                <flux:field>
                    <flux:label>{{ $type->needsPartner() ? 'First resident' : 'Resident' }}</flux:label>
                    <flux:select name="resident_id" required>
                        <flux:select.option value="">{{ $residents->isEmpty() ? 'Search for the resident first' : 'Choose the resident' }}</flux:select.option>
                        @foreach ($residents as $resident)
                            <flux:select.option :value="$resident->id" :selected="(string) old('resident_id') === (string) $resident->id">{{ $resident->full_name }} · {{ $resident->resident_number }}</flux:select.option>
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
</div>
@endsection
