<?php

namespace App\Actions;

use App\CertificateType;
use App\Exceptions\CertificateIssuanceException;
use App\Models\DocumentRequest;
use App\Models\IssuedCertificate;
use App\Models\Resident;
use F9WebLtd\QrCode\Generator;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class IssueCertificate
{
    public function __construct(
        private readonly FilesystemManager $filesystem,
        private readonly Generator $qrCode,
    ) {}

    /**
     * @param  array{
     *     certificate_type: string,
     *     resident_id?: int|null,
     *     resident_name: string,
     *     address?: string,
     *     purpose?: string|null
     * }  $attributes
     */
    public function handle(
        array $attributes,
        ?DocumentRequest $documentRequest,
        string $issuedBy,
        bool $requestMustBeReady = false,
    ): IssuedCertificate {
        $qrStoragePath = null;

        try {
            return DB::transaction(function () use (
                $attributes,
                $documentRequest,
                $issuedBy,
                $requestMustBeReady,
                &$qrStoragePath,
            ): IssuedCertificate {
                $certificateType = CertificateType::tryFromLabel($attributes['certificate_type'])
                    ?? throw CertificateIssuanceException::unsupportedCertificateType();

                $residentId = $documentRequest === null
                    ? ($attributes['resident_id'] ?? null)
                    : $documentRequest->resident_id;
                $resident = $residentId === null
                    ? null
                    : Resident::query()->lockForUpdate()->find($residentId);

                $this->ensureResidentIsEligible($resident, $certificateType);

                $lockedRequest = $documentRequest === null
                    ? DocumentRequest::query()->create([
                        'reference_code' => 'REQ-ONSITE-'.now()->format('Y').'-'.Str::upper(Str::random(8)),
                        'resident_id' => $resident?->id,
                        'source' => 'onsite',
                        'document_type' => $certificateType->value,
                        'full_name' => $attributes['resident_name'],
                        'address' => $attributes['address']
                            ?? throw new \LogicException('Onsite issuance requires a resident address.'),
                        'purpose' => $attributes['purpose'] ?? null,
                        'status' => 'ready_for_release',
                        'remarks' => 'Created from onsite certificate issuance.',
                    ])
                    : DocumentRequest::query()
                        ->lockForUpdate()
                        ->findOrFail($documentRequest->id);

                if ($lockedRequest->issuedCertificate()->exists()) {
                    throw CertificateIssuanceException::alreadyIssued();
                }

                if ($requestMustBeReady && $lockedRequest->status !== 'ready_for_release') {
                    throw CertificateIssuanceException::requestNotReady();
                }

                $certificateNumber = 'CERT-'.now()->format('Y').'-'.Str::upper(Str::random(8));
                $verificationCode = Str::upper(Str::random(16));
                $verificationUrl = route('certificate.verify', ['code' => $verificationCode]);
                $qrStoragePath = 'qrcodes/'.$certificateNumber.'.svg';
                $qrSvg = (string) $this->qrCode
                    ->format('svg')
                    ->size(300)
                    ->generate($verificationUrl);

                if (! $this->filesystem->disk('public')->put($qrStoragePath, $qrSvg)) {
                    throw CertificateIssuanceException::qrCodeCouldNotBeSaved();
                }

                $certificate = IssuedCertificate::query()->create([
                    'document_request_id' => $lockedRequest->id,
                    'resident_id' => $resident?->id,
                    'certificate_number' => $certificateNumber,
                    'verification_code' => $verificationCode,
                    'certificate_type' => $certificateType->value,
                    'resident_name' => $resident === null ? $attributes['resident_name'] : $resident->full_name,
                    'purpose' => $attributes['purpose'] ?? null,
                    'amount_paid' => $certificateType->fee(),
                    'issued_at' => now(),
                    'issued_by' => $issuedBy,
                    'qr_code_path' => '/storage/'.$qrStoragePath,
                ]);

                $lockedRequest->update([
                    'status' => 'released',
                    'remarks' => 'Certificate issued successfully.',
                ]);

                return $certificate;
            });
        } catch (Throwable $exception) {
            if ($qrStoragePath !== null) {
                $this->filesystem->disk('public')->delete($qrStoragePath);
            }

            throw $exception;
        }
    }

    private function ensureResidentIsEligible(?Resident $resident, CertificateType $certificateType): void
    {
        if ($resident === null) {
            return;
        }

        $eligibility = $resident->certificateEligibility($certificateType);

        if (! $eligibility['eligible']) {
            throw CertificateIssuanceException::residentIsNotEligible($eligibility['reasons'][0]);
        }
    }
}
