<?php

namespace App\Http\Controllers;

use App\CertificateType;
use App\Models\DocumentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DocumentRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', 'string', Rule::in(CertificateType::values())],
            'full_name' => 'required|string|max:255',
            'date_of_birth' => 'nullable|date',
            'address' => 'required|string',
            'email' => 'required|email|max:255',
            'purpose' => 'nullable|string|max:255',
            'business_name' => 'nullable|string|max:255',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $referenceCode = 'REQ-'.now()->format('Y').'-'.strtoupper(Str::random(6));
        $attachmentPath = $request->file('attachment')?->store(
            'document-request-attachments',
            'public'
        );

        $documentRequest = DocumentRequest::create([
            ...Arr::except($validated, ['attachment']),
            'reference_code' => $referenceCode,
            'attachment_path' => $attachmentPath ? Storage::url($attachmentPath) : null,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document request submitted successfully.',
            'reference_code' => $documentRequest->reference_code,
            'status' => $documentRequest->status,
        ]);
    }

    public function status(string $referenceCode): JsonResponse
    {
        $documentRequest = DocumentRequest::where('reference_code', $referenceCode)->first();

        if (! $documentRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Request not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'reference_code' => $documentRequest->reference_code,
            'document_type' => $documentRequest->document_type,
            'status' => $documentRequest->status,
            'remarks' => $documentRequest->remarks,
        ]);
    }
}
