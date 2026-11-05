{{-- Document request status as a badge: one label and tone per stored status (labels only; transitions live in the controller). --}}
@props(['status'])

@php
    [$label, $tone] = match ($status) {
        'pending' => ['Pending', 'warning'],
        'processing' => ['Processing', 'info'],
        'approved' => ['Approved', 'brand'],
        'ready_for_release' => ['Ready for release', 'success'],
        'released' => ['Released', 'neutral'],
        'rejected' => ['Rejected', 'danger'],
        default => [ucwords(str_replace('_', ' ', (string) $status)), 'neutral'],
    };
@endphp

<x-ui.status-badge :tone="$tone" :attributes="$attributes->merge(['data-request-status' => $status])">{{ $label }}</x-ui.status-badge>
