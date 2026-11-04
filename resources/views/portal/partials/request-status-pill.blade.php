@php
    $statusLabel = config('portal.request_statuses.'.$status.'.label') ?? ucfirst(str_replace('_', ' ', $status));
    $statusClass = ['processing' => 'status-progress', 'approved' => 'status-progress', 'ready_for_release' => 'status-ready', 'released' => 'status-done', 'rejected' => 'status-rejected'][$status] ?? '';
@endphp
<p class="status {{ $statusClass }}" data-status="{{ $status }}"><span class="sr-only"><span data-portal-i18n="Status:">{{ __('Status:') }}</span> </span><span data-portal-i18n="{{ $statusLabel }}">{{ __($statusLabel) }}</span></p>
