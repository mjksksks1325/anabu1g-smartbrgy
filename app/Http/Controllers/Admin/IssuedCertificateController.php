<?php

namespace App\Http\Controllers\Admin;

use App\Actions\IssueCertificate;
use App\AdditionalCertificateType;
use App\CertificateDocument;
use App\CertificateType;
use App\Exceptions\CertificateIssuanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreIssuedCertificateRequest;
use App\Models\DocumentRequest;
use App\Models\IssuedCertificate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IssuedCertificateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', DocumentRequest::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'certificate_type' => ['nullable', Rule::in(AdditionalCertificateType::allCertificateValues())],
            'status' => ['nullable', Rule::in(['valid', 'expired', 'revoked'])],
            'source' => ['nullable', Rule::in(['online', 'onsite'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $certificates = IssuedCertificate::query()
            ->with('documentRequest')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(fn (Builder $query) => $query
                    ->where('certificate_number', 'like', "%{$search}%")
                    ->orWhere('resident_name', 'like', "%{$search}%"));
            })
            ->when($filters['certificate_type'] ?? null, fn (Builder $query, string $type) => $query->where('certificate_type', $type))
            ->when(($filters['source'] ?? null) === 'online', fn (Builder $query) => $query->whereRelation('documentRequest', 'source', 'online'))
            ->when(($filters['source'] ?? null) === 'onsite', fn (Builder $query) => $query->whereDoesntHave('documentRequest', fn (Builder $query) => $query->where('source', 'online')))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('issued_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('issued_at', '<=', $date))
            ->when(($filters['status'] ?? null) === 'revoked', fn (Builder $query) => $query->whereNotNull('revoked_at'))
            ->when(($filters['status'] ?? null) === 'expired', fn (Builder $query) => $query->whereNull('revoked_at')->where(fn (Builder $query) => $this->whereExpired($query)))
            ->when(($filters['status'] ?? null) === 'valid', fn (Builder $query) => $query->whereNull('revoked_at')->whereNot(fn (Builder $query) => $this->whereExpired($query)))
            ->latest('issued_at')
            ->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 10))
            ->through(fn (IssuedCertificate $certificate): array => [
                'id' => $certificate->id,
                'certificate_number' => $certificate->certificate_number,
                'verification_code' => $certificate->verification_code,
                'certificate_type' => $certificate->certificate_type,
                'resident_name' => $certificate->resident_name,
                'source' => $certificate->documentRequest->source ?? 'onsite',
                'amount_paid' => $certificate->amount_paid,
                'issued_at' => $certificate->issued_at?->toIso8601String(),
                'issued_by' => $certificate->issued_by,
                'revoked_at' => $certificate->revoked_at?->toIso8601String(),
                'status' => $certificate->isRevoked() ? 'revoked' : ($certificate->isExpired() ? 'expired' : 'valid'),
                'valid_until' => $certificate->validUntil()?->toDateString(),
                'print_url' => route('staff.issued-certificates.print', $certificate),
                'verification_url' => route('certificate.verify', $certificate->verification_code),
            ]);

        return response()->json($certificates);
    }

    /**
     * Certificates past their last valid day, as IssuedCertificate::isExpired() decides: the stored expiry date, or for
     * older records without one, the issue date plus the period then in force (one year for IDs, three months otherwise).
     *
     * @param  Builder<IssuedCertificate>  $query
     */
    private function whereExpired(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query->whereNotNull('expires_on')->whereDate('expires_on', '<', today()))
            ->orWhere(fn (Builder $query) => $query->whereNull('expires_on')->whereNull('issued_at'))
            ->orWhere(fn (Builder $query) => $query->whereNull('expires_on')->where('certificate_type', CertificateType::BarangayId->value)->whereDate('issued_at', '<', today()->subMonthsNoOverflow(12)))
            ->orWhere(fn (Builder $query) => $query->whereNull('expires_on')->where('certificate_type', '!=', CertificateType::BarangayId->value)->whereDate('issued_at', '<', today()->subMonthsNoOverflow(3)));
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
        Gate::authorize('documents.issue');
        $details = $request->validate(StoreIssuedCertificateRequest::staffEnteredDetailRules());
        try {
            $certificate = $issueCertificate->handle(
                attributes: [
                    'certificate_type' => $documentRequest->document_type,
                    'resident_name' => $documentRequest->full_name,
                    'purpose' => $documentRequest->purpose,
                    'residing_since' => $details['residing_since'] ?? null,
                    'years_of_residency' => isset($details['years_of_residency']) ? (int) $details['years_of_residency'] : null,
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

    /**
     * What issuing this request would print, built from the same details issuance would freeze, so staff can check
     * it first. Nothing is saved: no certificate number, no QR code, a "not valid" watermark and printing disabled.
     * Missing details and eligibility problems are listed instead of previewed.
     */
    public function preview(Request $request, DocumentRequest $documentRequest, IssueCertificate $issueCertificate): Response
    {
        Gate::authorize('documents.issue');
        $details = $request->validate(StoreIssuedCertificateRequest::staffEnteredDetailRules());
        $certificateType = CertificateType::tryFromLabel((string) $documentRequest->document_type);
        $resident = $documentRequest->resident;
        $previewCertificate = new IssuedCertificate([
            'certificate_type' => $certificateType->value ?? $documentRequest->document_type,
            'resident_name' => $resident->full_name ?? $documentRequest->full_name,
            'purpose' => $documentRequest->purpose,
            'issued_at' => now(),
        ]);
        $problems = match (true) {
            $certificateType === null => ['This certificate type is not supported.'],
            $certificateType === CertificateType::BarangayId => ['Barangay ID cards have no preview. Issue the request to print the card.'],
            $resident === null && in_array($certificateType, [CertificateType::RegisteredVoterCertification, CertificateType::CertificateOfIndigency], true) => ['Select a verified resident record for this certificate.'],
            $resident !== null && ! $resident->certificateEligibility($certificateType)['eligible'] => $resident->certificateEligibility($certificateType)['reasons'],
            default => [],
        };

        if ($certificateType === null || $problems !== []) {
            return response()->view('admin.certificates.incomplete', ['certificate' => $previewCertificate, 'missingDetails' => [], 'problems' => $problems, 'isPreview' => true], 422);
        }

        $snapshot = $issueCertificate->snapshot($certificateType, $resident, [
            'resident_name' => $documentRequest->full_name,
            'residing_since' => $details['residing_since'] ?? null,
            'years_of_residency' => isset($details['years_of_residency']) ? (int) $details['years_of_residency'] : null,
        ], $documentRequest);
        $disk = Storage::disk('local');
        $photo = $resident?->photo_path !== null && $disk->exists($resident->photo_path) ? $resident->photo_path : null;
        $missingDetails = CertificateDocument::missingFor($certificateType, $snapshot, $documentRequest->purpose, $photo !== null);

        if ($missingDetails !== []) {
            return response()->view('admin.certificates.incomplete', ['certificate' => $previewCertificate, 'missingDetails' => $missingDetails, 'problems' => [], 'isPreview' => true], 422);
        }

        $previewCertificate->forceFill([
            'resident_snapshot' => $snapshot,
            'photo_path' => $photo,
            'expires_on' => now()->addMonthsNoOverflow($certificateType->validityPeriodInMonths())->toDateString(),
        ]);
        $photoUrl = $photo === null ? null : 'data:'.$disk->mimeType($photo).';base64,'.base64_encode((string) $disk->get($photo));

        return response()->view($certificateType->printView(), [
            'certificate' => $previewCertificate,
            'document' => new CertificateDocument($previewCertificate, isPreview: true, previewPhotoUrl: $photoUrl),
            'isPreview' => true,
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * The printable certificate in its official layout. A certificate whose record lacks a detail its template
     * states is not printed: staff see which details are missing instead.
     */
    public function print(Request $request, IssuedCertificate $issuedCertificate): Response
    {
        Gate::authorize('documents.print');

        if ($issuedCertificate->isRevoked()) {
            return response()->view('admin.certificates.unavailable', ['certificate' => $issuedCertificate, 'reason' => 'revoked'], 409);
        }

        $document = new CertificateDocument($issuedCertificate);

        $additionalType = AdditionalCertificateType::tryFrom((string) $issuedCertificate->certificate_type);
        if ($additionalType !== null) {
            return response()->view($additionalType->printView(), ['certificate' => $issuedCertificate, 'document' => $document, 'type' => $additionalType]);
        }

        // Issued before the official 2026 templates: reprint with the wording and details recorded at issuance.
        if ($issuedCertificate->template_version === null && $document->type() !== CertificateType::BarangayId) {
            if ($issuedCertificate->resident_snapshot === null) {
                return response()->view('admin.certificates.unavailable', ['certificate' => $issuedCertificate, 'reason' => 'not-reconstructable'], 409);
            }

            DB::table('administrative_audits')->insert([
                'user_id' => $request->user()->id, 'actor' => $request->user()->name, 'action' => 'admin.issued-certificates.historical-reprint',
                'type' => 'cert', 'record' => (string) $issuedCertificate->id, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return response()->view('admin.certificates.historical', ['certificate' => $issuedCertificate, 'document' => $document]);
        }

        $missingDetails = $document->missingDetails();

        if ($missingDetails !== []) {
            return response()->view('admin.certificates.incomplete', ['certificate' => $issuedCertificate, 'missingDetails' => $missingDetails], 409);
        }

        return response()->view($document->type()?->printView() ?? 'admin.certificates.print', [
            'certificate' => $issuedCertificate,
            'document' => $document,
        ]);
    }

    /**
     * Revokes an issued certificate: it stays on record with who revoked it, when and why, can no longer be printed,
     * and QR verification reports it as revoked. Requires the documents.revoke permission and, through the route, the
     * active Barangay Captain or Super Admin password. A revocation is final and creates no replacement.
     */
    public function revoke(Request $request, IssuedCertificate $issuedCertificate): JsonResponse
    {
        Gate::authorize('documents.revoke');
        $validated = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);

        return DB::transaction(function () use ($request, $issuedCertificate, $validated): JsonResponse {
            $certificate = IssuedCertificate::query()->lockForUpdate()->findOrFail($issuedCertificate->id);

            if ($certificate->isRevoked()) {
                return response()->json(['message' => __('This certificate was already revoked.')], 409);
            }

            $certificate->forceFill([
                'revoked_at' => now(),
                'revoked_by' => $request->user()->id,
                'revocation_reason' => trim($validated['reason']),
            ])->save();

            return response()->json([
                'message' => __('Certificate revoked. It stays on record, cannot be printed, and QR verification shows it as revoked.'),
                'certificate' => ['id' => $certificate->id, 'certificate_number' => $certificate->certificate_number, 'revoked_at' => $certificate->revoked_at?->toIso8601String()],
            ]);
        });
    }

    public function photo(IssuedCertificate $issuedCertificate): StreamedResponse
    {
        Gate::authorize('documents.print');
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
                'print_url' => route('staff.issued-certificates.print', [
                    'issuedCertificate' => $certificate,
                ]),
            ]);
        }

        return redirect()
            ->route('staff.document-requests.index')
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
