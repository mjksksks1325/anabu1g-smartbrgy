@extends('layouts.admin-iot')
@section('title', 'Document Requests')
@section('content')
<div class="document-requests-index">
    <div class="eyebrow">Records / Document Requests</div>
    <div class="page-heading"><div><h1>Document Requests</h1><p>Review resident submissions and follow each request through to release.</p></div></div>

    <div class="civic-table-wrap"><table class="civic-table">

        <thead>
            <tr>
                <th>Reference Code</th>
                <th>Resident</th>
                <th>Document</th>
                <th>Status</th>
                <th>Date Submitted</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>

        @forelse ($requests as $request)

            <tr>
                <td>{{ $request->reference_code }}</td>

                <td>{{ $request->full_name }}</td>

                <td>{{ $request->document_type }}</td>

                <td><span class="civic-status">{{ str_replace('_', ' ', $request->status) }}</span></td>

                <td>
                    {{ $request->created_at->format('M d, Y h:i A') }}
                </td>

                <td>
                    <a
                        href="{{ route('staff.document-requests.show', $request) }}"
                        class="civic-link"
                    >
                        View
                    </a>
                </td>
            </tr>

        @empty

            <tr>
                <td colspan="6" class="civic-empty">
                    No document requests found.
                </td>
            </tr>

        @endforelse

        </tbody>

    </table></div>
<div style="margin-top:24px;">{{ $requests->links() }}</div>

</div>
@endsection
