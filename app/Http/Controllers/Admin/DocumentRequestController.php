<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentRequestController extends Controller
{
    public function index(): View
    {
        $requests = DocumentRequest::query()->latest()->get();

        return view('admin.document-requests.index', compact('requests'));
    }

    public function show(DocumentRequest $documentRequest): View
    {
        return view('admin.document-requests.show', compact('documentRequest'));
    }

    public function updateStatus(
        Request $request,
        DocumentRequest $documentRequest,
    ): JsonResponse|RedirectResponse {
        $validated = $request->validate([
            'status' => 'required|in:pending,processing,approved,ready_for_release,rejected',
            'remarks' => 'nullable|string|max:1000',
        ]);

        if ($documentRequest->issuedCertificate()->exists()) {
            $message = 'This request already has an issued certificate and its status is locked.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 409);
            }

            return back()->withErrors(['status' => $message]);
        }

        $documentRequest->update([
            'status' => $validated['status'],
            'remarks' => $validated['remarks'] ?? null,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Document request updated successfully.',
                'request' => $documentRequest->fresh(),
            ]);
        }

        return back()->with('success', 'Document request updated successfully.');
    }

    public function live(): JsonResponse
    {
        return response()->json(DocumentRequest::query()->latest()->get());
    }
}
