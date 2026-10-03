<?php

namespace App\Http\Controllers;

use App\Actions\CheckRequestRestrictions;
use App\CertificateType;
use App\Http\Middleware\EnsureResidentAccount;
use App\Models\DocumentRequest;
use App\Models\Resident;
use App\Models\ResidentRequestRestriction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DocumentRequestController extends Controller
{
    public function csrfToken(Request $request): JsonResponse
    {
        return response()
            ->json(['token' => $request->session()->token()])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', 'string', Rule::in(CertificateType::values())],
            'purpose' => 'required|string|max:255',
            'business_name' => 'nullable|string|max:255',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        return DB::transaction(function () use ($request, $validated): JsonResponse {
            $resident = Resident::query()->whereKey($request->user()->resident_id)->lockForUpdate()->firstOrFail();
            if (app(CheckRequestRestrictions::class)->active($resident, CertificateType::from($validated['document_type'])) !== null) {
                throw ValidationException::withMessages(['document_type' => ResidentRequestRestriction::MESSAGE]);
            }
            $referenceCode = 'REQ-'.now()->format('Y').'-'.strtoupper(Str::random(6));
            $attachmentPath = $request->file('attachment')?->store(
                'document-request-attachments',
                'local'
            );

            $documentRequest = DocumentRequest::create([
                ...Arr::except($validated, ['attachment']),
                'resident_id' => $resident->id,
                'full_name' => $resident->full_name,
                'date_of_birth' => $resident->date_of_birth->toDateString(),
                'address' => $resident->address,
                'email' => $request->user()->email,
                'reference_code' => $referenceCode,
                'source' => 'online',
                'private_attachment_path' => $attachmentPath,
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document request submitted successfully.',
                'reference_code' => $documentRequest->reference_code,
                'status' => $documentRequest->status,
            ]);
        });
    }

    public function status(Request $request, string $referenceCode): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canUseResidentPortal(), 403, EnsureResidentAccount::ASSISTANCE);
        $query = $user->resident->documentRequests();
        $documentRequest = $query->where('reference_code', $referenceCode)->first();

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
            'rejection_reason' => $documentRequest->status === 'rejected'
                ? $documentRequest->rejection_reason
                : null,
        ]);
    }
}
