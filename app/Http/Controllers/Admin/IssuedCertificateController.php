<?php

namespace App\Http\Controllers\Admin;

use App\Actions\IssueCertificate;
use App\CertificateType;
use App\Exceptions\CertificateIssuanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreIssuedCertificateRequest;
use App\Models\DocumentRequest;
use App\Models\IssuedCertificate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IssuedCertificateController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', DocumentRequest::class);
        $certificates = IssuedCertificate::query()
            ->with('documentRequest')
            ->latest('issued_at')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (IssuedCertificate $certificate): array => [
                'id' => $certificate->id,
                'certificate_number' => $certificate->certificate_number,
                'verification_code' => $certificate->verification_code,
                'certificate_type' => $certificate->certificate_type,
                'resident_name' => $certificate->resident_name,
                'source' => $certificate->documentRequest->source ?? 'onsite',
                'amount_paid' => $certificate->amount_paid,
                'issued_at' => $certificate->issued_at?->toIso8601String(),
                'issued_by' => $certificate->issued_by,
                'print_url' => route('admin.issued-certificates.print', $certificate),
                'verification_url' => route('certificate.verify', $certificate->verification_code),
            ]);

        return response()->json($certificates);
    }

    public function store(
        StoreIssuedCertificateRequest $request,
        IssueCertificate $issueCertificate,
    ): JsonResponse|RedirectResponse {
        try {
            $certificate = $issueCertificate->handle(
                attributes: $request->certificateAttributes(),
                documentRequest: null,
                issuedBy: $request->user()->name,
            );
        } catch (CertificateIssuanceException $exception) {
            return $this->issuanceFailureResponse($request, $exception);
        }

        return $this->issuanceSuccessResponse($request, $certificate);
    }

    public function issueFromRequest(
        Request $request,
        DocumentRequest $documentRequest,
        IssueCertificate $issueCertificate,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('update', $documentRequest);
        $validated = $request->validate(['expires_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today']]);
        try {
            $certificate = $issueCertificate->handle(
                attributes: [
                    'certificate_type' => $documentRequest->document_type,
                    'resident_name' => $documentRequest->full_name,
                    'purpose' => $documentRequest->purpose,
                    'expires_on' => $validated['expires_on'] ?? null,
                ],
                documentRequest: $documentRequest,
                issuedBy: $request->user()->name,
                requestMustBeReady: true,
            );
        } catch (CertificateIssuanceException $exception) {
            return $this->issuanceFailureResponse($request, $exception);
        }

        return $this->issuanceSuccessResponse($request, $certificate);
    }

    public function print(IssuedCertificate $issuedCertificate): View
    {
        Gate::authorize('viewAny', DocumentRequest::class);
        $view = CertificateType::tryFromLabel($issuedCertificate->certificate_type)?->printView()
            ?? 'admin.certificates.print';

        return view($view, ['certificate' => $issuedCertificate]);
    }

    public function photo(IssuedCertificate $issuedCertificate): StreamedResponse
    {
        Gate::authorize('viewAny', DocumentRequest::class);
        $disk = Storage::disk('local');
        abort_unless($issuedCertificate->photo_path !== null && $disk->exists($issuedCertificate->photo_path), 404);

        return $disk->response($issuedCertificate->photo_path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function issuanceSuccessResponse(
        Request $request,
        IssuedCertificate $certificate,
    ): JsonResponse|RedirectResponse {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Certificate issued successfully.',
                'certificate' => $certificate,
                'verification_url' => route('certificate.verify', [
                    'code' => $certificate->verification_code,
                ]),
                'print_url' => route('admin.issued-certificates.print', [
                    'issuedCertificate' => $certificate,
                ]),
            ]);
        }

        return redirect()
            ->route('admin.document-requests.index')
            ->with('success', 'Certificate issued successfully.');
    }

    private function issuanceFailureResponse(
        Request $request,
        CertificateIssuanceException $exception,
    ): JsonResponse|RedirectResponse {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], $exception->httpStatus());
        }

        return back()
            ->withInput()
            ->withErrors(['certificate' => $exception->getMessage()]);
    }
}
