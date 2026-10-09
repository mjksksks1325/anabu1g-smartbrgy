@php
    $statusDetails = [
        'pending' => ['Received', 'Natanggap na. Hinihintay pa ang review ng barangay staff.', ''],
        'processing' => ['Processing', 'Pinoproseso na ng barangay staff.', 'status-progress'],
        'approved' => ['Approved', 'Aprubado na. Hintaying maging Ready for release bago pumunta sa Barangay Hall.', 'status-progress'],
        'ready_for_release' => ['Ready for release', 'Puwede nang kunin sa Barangay Hall.', 'status-ready'],
        'released' => ['Released', 'Nakuha na ang dokumento.', 'status-done'],
        'rejected' => ['Not approved', 'Hindi naaprubahan ang request.', 'status-rejected'],
    ];
    [$statusLabel, $statusNote, $statusClass] = $statusDetails[$item->status] ?? [ucfirst(str_replace('_', ' ', $item->status)), '', ''];
@endphp
<li @class(['request-item', 'is-ready' => $item->status === 'ready_for_release', 'is-rejected' => $item->status === 'rejected']) data-request-id="{{ $item->id }}" data-request-version="{{ $item->historyVersion() }}">
    <div class="request-item-head">
        <div>
            <h2><span data-portal-i18n="{{ $item->document_type }}">{{ __($item->document_type) }}</span></h2>
            <dl class="request-meta">
                <div><dt><span data-portal-i18n="Reference no.">{{ __('Reference no.') }}</span></dt><dd class="reference">{{ $item->reference_code }}</dd></div>
                <div><dt><span data-portal-i18n="Na-submit">{{ __('Na-submit') }}</span></dt><dd><time datetime="{{ $item->created_at->toDateString() }}">{{ $item->created_at->format('M j, Y') }}</time></dd></div>
            </dl>
        </div>
        <p class="status {{ $statusClass }}"><span class="sr-only"><span data-portal-i18n="Status:">{{ __('Status:') }}</span> </span><span data-portal-i18n="{{ $statusLabel }}">{{ __($statusLabel) }}</span></p>
    </div>
    @if($statusNote)<p class="request-status-note"><span data-portal-i18n="{{ $statusNote }}">{{ __($statusNote) }}</span></p>@endif

    @if($item->status === 'ready_for_release')
        <div class="request-callout request-callout-ready">
            <strong><span data-portal-i18n="Paano kunin">{{ __('Paano kunin') }}</span></strong>
            <ul>
                <li><span data-portal-i18n="Pumunta sa Barangay Hall ng Anabu I-G.">{{ __('Pumunta sa Barangay Hall ng Anabu I-G.') }}</span> <span class="tag-unconfirmed"><span data-portal-i18n="Hindi pa kumpirmado">{{ __('Hindi pa kumpirmado') }}</span></span> <span data-portal-i18n="ang office hours.">{{ __('ang office hours.') }}</span></li>
                <li><span data-portal-i18n="Dalhin ang valid ID at ang reference number na">{{ __('Dalhin ang valid ID at ang reference number na') }}</span> {{ $item->reference_code }}.</li>
                <li><span data-portal-i18n="Bayaran ang fee sa Barangay Hall, kung mayroon.">{{ __('Bayaran ang fee sa Barangay Hall, kung mayroon.') }}</span></li>
            </ul>
        </div>
    @endif
    @if($item->status === 'rejected')
        <div class="request-callout request-callout-rejected">
            <strong><span data-portal-i18n="Dahilan">{{ __('Dahilan') }}</span></strong>
            @if($item->rejection_reason){{ $item->rejection_reason }}@else<span data-portal-i18n="Walang nakasulat na dahilan. Magtanong sa Barangay Hall.">{{ __('Walang nakasulat na dahilan. Magtanong sa Barangay Hall.') }}</span>@endif
            <p class="request-callout-next"><span data-portal-i18n="Puwedeng mag-submit ng bagong request kapag naayos na ang problema.">{{ __('Puwedeng mag-submit ng bagong request kapag naayos na ang problema.') }}</span></p>
        </div>
    @endif
    @if($item->remarks)
        <div class="request-callout request-callout-remarks"><strong><span data-portal-i18n="Paalala mula sa barangay">{{ __('Paalala mula sa barangay') }}</span></strong>{{ $item->remarks }}</div>
    @endif
</li>
