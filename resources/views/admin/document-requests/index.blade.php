<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Document Requests</title>

    <link rel="icon" type="image/jpeg" href="{{ asset('images/anabu-logo.jpg') }}">
    <link rel="stylesheet" href="{{ asset('css/government.css') }}">
</head>

<body class="civic-page">
@include('partials.civic-header')

<main class="civic-main" id="main-content" tabindex="-1">

    <h1 class="civic-page-title">Document Requests</h1>
<p class="civic-lead">Review resident submissions and follow each request through to release.</p>

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

                <td class="civic-status">
                    {{ str_replace('_', ' ', $request->status) }}
                </td>

                <td>
                    {{ $request->created_at->format('M d, Y h:i A') }}
                </td>

                <td>
                    <a
                        href="{{ route('admin.document-requests.show', $request) }}"
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

</main>
@include('partials.civic-footer')
</body>
</html>