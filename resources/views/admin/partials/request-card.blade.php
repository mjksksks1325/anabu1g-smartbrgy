<div class="request-card"
     data-request-search="{{ strtolower(
        $request->reference_code . ' ' .
        $request->full_name . ' ' .
        $request->document_type . ' ' .
        $request->status
     ) }}">

    <div class="request-card-name">
        👤 {{ $request->full_name }}
    </div>

    <div class="request-card-type">
        {{ $request->document_type }}
    </div>

    <div class="request-card-ref">
        {{ $request->reference_code }}
    </div>

    <div class="request-card-source">
        <span class="badge badge-blue">Online</span>
    </div>

    <div class="request-card-actions">

        @if ($stage === 'pending')
            <button type="button"
                    class="btn btn-primary btn-sm"
                    onclick="updateRequestStage({{ $request->id }}, 'processing')">
                Process
            </button>
        @endif

        @if ($stage === 'processing')
            <button type="button"
                    class="btn btn-green btn-sm"
                    onclick="updateRequestStage({{ $request->id }}, 'ready_for_release')">
                Ready to Print
            </button>
        @endif

        @if ($stage === 'ready')
            <button type="button"
                    class="btn btn-green btn-sm"
                    onclick="openDocumentRequestModal(
                        @js($request->id),
                        @js($request->reference_code),
                        @js($request->full_name),
                        @js($request->document_type),
                        @js($request->date_of_birth),
                        @js($request->address),
                        @js($request->contact_number),
                        @js($request->purpose),
                        @js($request->business_name),
                        @js($request->status),
                        @js($request->remarks)
                    )">
                Print & Release
            </button>
        @endif

        <button type="button"
                class="btn btn-sm"
                onclick="openDocumentRequestModal(
                    @js($request->id),
                    @js($request->reference_code),
                    @js($request->full_name),
                    @js($request->document_type),
                    @js($request->date_of_birth),
                    @js($request->address),
                    @js($request->contact_number),
                    @js($request->purpose),
                    @js($request->business_name),
                    @js($request->status),
                    @js($request->remarks)
                )">
            View
        </button>

    </div>
</div><div class="request-card"
     data-request-search="{{ strtolower(
        $request->reference_code . ' ' .
        $request->full_name . ' ' .
        $request->document_type . ' ' .
        $request->status
     ) }}">

    <div class="request-card-name">
        👤 {{ $request->full_name }}
    </div>

    <div class="request-card-type">
        {{ $request->document_type }}
    </div>

    <div class="request-card-ref">
        {{ $request->reference_code }}
    </div>

    <div class="request-card-source">
        <span class="badge badge-blue">Online</span>
    </div>

    <div class="request-card-actions">

        @if ($stage === 'pending')
            <button type="button"
                    class="btn btn-primary btn-sm"
                    onclick="updateRequestStage({{ $request->id }}, 'processing')">
                Process
            </button>
        @endif

        @if ($stage === 'processing')
            <button type="button"
                    class="btn btn-green btn-sm"
                    onclick="updateRequestStage({{ $request->id }}, 'ready_for_release')">
                Ready to Print
            </button>
        @endif

        @if ($stage === 'ready')
            <button type="button"
                    class="btn btn-green btn-sm"
                    onclick="openDocumentRequestModal(
                        @js($request->id),
                        @js($request->reference_code),
                        @js($request->full_name),
                        @js($request->document_type),
                        @js($request->date_of_birth),
                        @js($request->address),
                        @js($request->contact_number),
                        @js($request->purpose),
                        @js($request->business_name),
                        @js($request->status),
                        @js($request->remarks)
                    )">
                Print & Release
            </button>
        @endif

        <button type="button"
                class="btn btn-sm"
                onclick="openDocumentRequestModal(
                    @js($request->id),
                    @js($request->reference_code),
                    @js($request->full_name),
                    @js($request->document_type),
                    @js($request->date_of_birth),
                    @js($request->address),
                    @js($request->contact_number),
                    @js($request->purpose),
                    @js($request->business_name),
                    @js($request->status),
                    @js($request->remarks)
                )">
            View
        </button>

    </div>
</div>