<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateDocumentRequestStatusRequest;
use App\Models\DocumentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DocumentRequestController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', DocumentRequest::class);
        $requests = DocumentRequest::query()->latest()->paginate(25);

        return view('admin.document-requests.index', compact('requests'));
    }

    public function show(DocumentRequest $documentRequest): View
    {
        Gate::authorize('view', $documentRequest);

        return view('admin.document-requests.show', compact('documentRequest'));
    }

    public function updateStatus(
        UpdateDocumentRequestStatusRequest $request,
        DocumentRequest $documentRequest,
    ): JsonResponse|RedirectResponse {
        $validated = $request->validated();
        $lockedRequest = DB::transaction(function () use ($request, $documentRequest, $validated): ?DocumentRequest {
            $locked = DocumentRequest::query()->lockForUpdate()->findOrFail($documentRequest->id);

            if ($locked->issuedCertificate()->exists()) {
                return null;
            }

            $isRejected = $validated['status'] === 'rejected';
            $locked->update([
                'status' => $validated['status'],
                'remarks' => $validated['remarks'] ?? $locked->remarks,
                'rejection_reason' => $isRejected ? $validated['rejection_reason'] : null,
                'rejected_at' => $isRejected ? now() : null,
                'rejected_by' => $isRejected ? $request->user()->id : null,
            ]);

            return $locked;
        });

        if ($lockedRequest === null) {
            $message = 'This request already has an issued certificate and its status is locked.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 409);
            }

            return back()->withErrors(['status' => $message]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $validated['status'] === 'rejected'
                    ? 'Document request rejected with a recorded reason.'
                    : 'Document request updated successfully.',
                'request' => $lockedRequest->fresh(),
            ]);
        }

        return back()->with('success', 'Document request updated successfully.');
    }

    public function live(): JsonResponse
    {
        Gate::authorize('viewAny', DocumentRequest::class);

        return response()->json(DocumentRequest::query()->latest()->get());
    }
}
