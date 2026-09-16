<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Request Details</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('images/anabu-logo.jpg') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>

<body>

<div style="min-height:100vh; padding:28px;">

    {{-- Page Header --}}
    <div class="page-header-row" style="margin-bottom:20px;">
        <div class="page-header" style="margin-bottom:0;">
            <h1>Document Request <span>Details</span></h1>
            <p>Review and manage the resident's document request</p>
        </div>

        <a href="{{ route('admin.dashboard') }}"
           class="btn btn-outline"
           style="text-decoration:none;">
            ← Back to Dashboard
        </a>
    </div>

    {{-- Success Message --}}
    @if (session('success'))
        <div class="card"
             style="padding:12px 16px; margin-bottom:16px; border-color:var(--border-green);">
            <span style="color:var(--green-500);">
                ✓ {{ session('success') }}
            </span>
        </div>
    @endif

    {{-- Request Details --}}
    <div class="card" style="margin-bottom:18px;">

        <div class="card-header">
            <div>
                <div class="card-title">📄 Request Information</div>
                <div class="card-sub">
                    Reference: {{ $documentRequest->reference_code }}
                </div>
            </div>

            <span class="badge badge-blue">
                {{ ucwords(str_replace('_', ' ', $documentRequest->status)) }}
            </span>
        </div>

        <div style="
            display:grid;
            grid-template-columns:repeat(2, minmax(0, 1fr));
            gap:18px;
            padding:18px;
        ">

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

    {{-- Status Management --}}
    <div class="card">

        <div class="card-header">
            <div>
                <div class="card-title">⚙️ Request Status</div>
                <div class="card-sub">
                    Update the processing status and staff remarks
                </div>
            </div>
        </div>

        <form method="POST"
              action="{{ route('admin.document-requests.update-status', $documentRequest) }}"
              style="padding:18px;">

            @csrf
            @method('PATCH')

            <div style="margin-bottom:16px;">
                <label class="stat-label">Status</label>

                <select name="status"
                        class="form-control"
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

                    <option value="released"
                        @selected($documentRequest->status === 'released')>
                        Released
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
                          class="form-control"
                          rows="4"
                          style="width:100%; margin-top:6px;"
                          placeholder="Add optional staff remarks...">{{ old('remarks', $documentRequest->remarks) }}</textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">

                <a href="{{ route('admin.dashboard') }}"
                   class="btn btn-outline"
                   style="text-decoration:none;">
                    Cancel
                </a>

                <button type="submit" class="btn btn-green">
                    ✓ Update Request
                </button>

            </div>

        </form>

    </div>

</div>

</body>
</html>
