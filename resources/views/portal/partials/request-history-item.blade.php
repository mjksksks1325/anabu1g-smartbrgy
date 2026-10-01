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
            <h2>{{ $item->document_type }}</h2>
            <dl class="request-meta">
                <div><dt>Reference no.</dt><dd class="reference">{{ $item->reference_code }}</dd></div>
                <div><dt>Na-submit</dt><dd><time datetime="{{ $item->created_at->toDateString() }}">{{ $item->created_at->format('M j, Y') }}</time></dd></div>
            </dl>
        </div>
        <p class="status {{ $statusClass }}"><span class="sr-only">Status: </span>{{ $statusLabel }}</p>
    </div>
    @if($statusNote)<p class="request-status-note">{{ $statusNote }}</p>@endif

    @if($item->status === 'ready_for_release')
        <div class="request-callout request-callout-ready">
            <strong>Paano kunin</strong>
            <ul>
                <li>Pumunta sa Barangay Hall ng Anabu I-G. <span class="tag-unconfirmed">Hindi pa kumpirmado</span> ang office hours.</li>
                <li>Dalhin ang valid ID at ang reference number na {{ $item->reference_code }}.</li>
                <li>Bayaran ang fee sa Barangay Hall, kung mayroon.</li>
            </ul>
        </div>
    @endif
    @if($item->status === 'rejected')
        <div class="request-callout request-callout-rejected">
            <strong>Dahilan</strong>
            {{ $item->rejection_reason ?: 'Walang nakasulat na dahilan. Magtanong sa Barangay Hall.' }}
            <p class="request-callout-next">Puwedeng mag-submit ng bagong request kapag naayos na ang problema.</p>
        </div>
    @endif
    @if($item->remarks)
        <div class="request-callout request-callout-remarks"><strong>Paalala mula sa barangay</strong>{{ $item->remarks }}</div>
    @endif
</li>
