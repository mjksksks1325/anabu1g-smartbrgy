@extends('layouts.admin-iot')
@section('title', 'Document Request Details')
@section('content')
<div class="document-request-detail">
    <div class="eyebrow">Records / Document Requests</div>

    {{-- Page Header --}}
    <div class="page-header-row" style="margin-bottom:20px;">
        <div class="page-header" style="margin-bottom:0;">
            <h1>Document Request <span>Details</span></h1>
            <p>Review and manage the resident's document request</p>
        </div>

        <a href="{{ route(App\StaffPermissions::landing(auth()->user())) }}"
           class="btn btn-outline"
           style="text-decoration:none;">
            ← Back to Dashboard
        </a>
    </div>

    {{-- Success Message --}}
    @if (session('success'))
        <div class="card request-success" role="status">
            <span>
                 {{ session('success') }}
            </span>
        </div>
    @endif

    @if ($errors->any())
        <div class="card" role="alert"><strong>Unable to update request</strong><ul>
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul></div>
    @endif
    {{-- Request Details --}}
    <div class="card" style="margin-bottom:18px;">

        <div class="card-header">
            <div>
                <div class="card-title"> Request Information</div>
                <div class="card-sub">
                    Reference: {{ $documentRequest->reference_code }}
                </div>
            </div>

            <span class="badge badge-blue">
                {{ ucwords(str_replace('_', ' ', $documentRequest->status)) }}
            </span>
        </div>

        <div class="request-detail-grid">

            <div>
                <div class="stat-label">Reference Code</div>
                <div style="font-family:var(--font-mono);">
                    {{ $documentRequest->reference_code }}
                </div>
            </div>

            <div>
                <div class="stat-label">Resident Name</div>
                <div>{{ $documentRequest->full_name }}</div>
            </div>

            <div>
                <div class="stat-label">Document Type</div>
                <div>{{ $documentRequest->document_type }}</div>
            </div>

            <div>
                <div class="stat-label">Date of Birth</div>
                <div>
                    {{ $documentRequest->date_of_birth ?? 'Not provided' }}
                </div>
            </div>

            <div>
                <div class="stat-label">Email Address</div>
                <div>
                    {{ $documentRequest->email ?? 'Not provided' }}
                </div>
            </div>

            <div>
                <div class="stat-label">Date Submitted</div>
                <div>
                    {{ $documentRequest->created_at->format('M d, Y h:i A') }}
                </div>
            </div>

            <div style="grid-column:1 / -1;">
                <div class="stat-label">Address</div>
                <div>{{ $documentRequest->address }}</div>
            </div>

            <div style="grid-column:1 / -1;">
                <div class="stat-label">Purpose</div>
                <div>
                    {{ $documentRequest->purpose ?? 'Not provided' }}
                </div>
            </div>

            @if ($documentRequest->business_name)
                <div style="grid-column:1 / -1;">
                    <div class="stat-label">Business Name</div>
                    <div>{{ $documentRequest->business_name }}</div>
                </div>
            @endif

        </div>
    </div>

    @can('documents.process')
    {{-- Status Management --}}
    <div class="card">

        <div class="card-header">
            <div>
                <div class="card-title"> Request Status</div>
                <div class="card-sub">
                    Update the processing status and staff remarks
                </div>
            </div>
        </div>

        <form method="POST"
              action="{{ route('staff.document-requests.update-status', $documentRequest) }}"
              style="padding:18px;">

            @csrf
            @method('PATCH')

            <div style="margin-bottom:16px;">
                <label class="stat-label">Status</label>

                <select name="status"
                        class="form-input"
                        style="width:100%; margin-top:6px;"
                        required>

                    <option value="pending"
                        @selected($documentRequest->status === 'pending')>
                        Pending
                    </option>

                    <option value="processing"
                        @selected($documentRequest->status === 'processing')>
                        Processing
                    </option>

                    <option value="approved"
                        @selected($documentRequest->status === 'approved')>
                        Approved
                    </option>

                    <option value="ready_for_release"
                        @selected($documentRequest->status === 'ready_for_release')>
                        Ready for Release
                    </option>

                    <option value="rejected"
                        @selected($documentRequest->status === 'rejected')>
                        Rejected
                    </option>

                </select>
            </div>

            <div style="margin-bottom:18px;">
                <label class="stat-label">Remarks</label>

                <textarea name="remarks"
                          class="form-input"
                          rows="4"
                          style="width:100%; margin-top:6px;"
                          placeholder="Add optional staff remarks...">{{ old('remarks', $documentRequest->remarks) }}</textarea>
            </div>

            <div class="form-group">
                <label for="rejection_reason" class="form-label">Reason for rejection (required when rejecting)</label>
                <textarea id="rejection_reason" name="rejection_reason" class="form-input" rows="3" maxlength="1000">{{ old('rejection_reason', $documentRequest->rejection_reason) }}</textarea>
                <p>Provide at least 10 characters explaining the decision.</p>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">

                <a href="{{ route(App\StaffPermissions::landing(auth()->user())) }}"
                   class="btn btn-outline"
                   style="text-decoration:none;">
                    Cancel
                </a>

                <button type="submit" class="btn btn-green">
                     Update Request
                </button>

            </div>

        </form>

    </div>

</div>
@endcan
@endsection
