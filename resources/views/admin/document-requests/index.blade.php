<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Document Requests</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 30px;
        }

        .container {
            max-width: 1200px;
            margin: auto;
        }

        h1 {
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #166534;
            color: white;
        }

        .status {
            text-transform: capitalize;
        }

        .btn {
            display: inline-block;
            padding: 7px 12px;
            background: #166534;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .empty {
            text-align: center;
            padding: 30px;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Document Requests</h1>

    <table>

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

                <td class="status">
                    {{ str_replace('_', ' ', $request->status) }}
                </td>

                <td>
                    {{ $request->created_at->format('M d, Y h:i A') }}
                </td>

                <td>
                    <a
                        href="{{ route('admin.document-requests.show', $request) }}"
                        class="btn"
                    >
                        View
                    </a>
                </td>
            </tr>

        @empty

            <tr>
                <td colspan="6" class="empty">
                    No document requests found.
                </td>
            </tr>

        @endforelse

        </tbody>

    </table>

</div>

</body>
</html>