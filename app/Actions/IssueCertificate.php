<?php

namespace App\Actions;

use App\CertificateDocument;
use App\CertificateType;
use App\Exceptions\CertificateIssuanceException;
use App\Models\DocumentRequest;
use App\Models\IssuedCertificate;
use App\Models\Resident;
use Carbon\CarbonImmutable;
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
     *     purpose?: string|null,
     *     residing_since?: string|null,
     *     years_of_residency?: int|null
     * }  $attributes
     */
    public function handle(
        array $attributes,
        ?DocumentRequest $documentRequest,
        string $issuedBy,
        bool $requestMustBeReady = false,
    ): IssuedCertificate {
        $qrStoragePath = null;
        $photoStoragePath = null;

        try {
            return DB::transaction(function () use (
                $attributes,
                $documentRequest,
                $issuedBy,
                $requestMustBeReady,
                &$qrStoragePath,
                &$photoStoragePath,
            ): IssuedCertificate {
                $certificateType = CertificateType::tryFromLabel($attributes['certificate_type'])
                    ?? throw CertificateIssuanceException::unsupportedCertificateType();

                $residentId = $documentRequest === null
                    ? ($attributes['resident_id'] ?? null)
                    : $documentRequest->resident_id;
                $resident = $residentId === null
                    ? null
                    : Resident::query()->lockForUpdate()->find($residentId);

                if ($resident !== null && in_array($certificateType, [CertificateType::RegisteredVoterCertification, CertificateType::CertificateOfIndigency], true)) {
                    $resident->setRelation('voterRegistration', $resident->voterRegistration()->lockForUpdate()->first());
                }

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

                $snapshot = $this->snapshot($certificateType, $resident, $attributes, $documentRequest);
                $this->ensureDetailsAreComplete(
                    $certificateType,
                    $snapshot,
                    $attributes['purpose'] ?? null,
                    $resident?->photo_path !== null && $this->filesystem->disk('local')->exists($resident->photo_path),
                    linkedToResident: $resident !== null,
                );

                $issuedAt = now();
                $certificateNumber = 'CERT-'.$issuedAt->format('Y').'-'.Str::upper(Str::random(8));
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

                if ($resident?->photo_path !== null && $this->filesystem->disk('local')->exists($resident->photo_path)) {
                    $photoStoragePath = 'certificate-photos/'.$certificateNumber.'.'.pathinfo($resident->photo_path, PATHINFO_EXTENSION);
                    if (! $this->filesystem->disk('local')->copy($resident->photo_path, $photoStoragePath)) {
                        throw new \RuntimeException('Certificate photo could not be saved.');
                    }
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
                    'issued_at' => $issuedAt,
                    'expires_on' => $issuedAt->copy()->addMonthsNoOverflow($certificateType->validityPeriodInMonths())->toDateString(),
                    'photo_path' => $photoStoragePath,
                    'resident_snapshot' => $snapshot,
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

            if ($photoStoragePath !== null) {
                $this->filesystem->disk('local')->delete($photoStoragePath);
            }

            throw $exception;
        }
    }

    /**
     * The details the certificate prints, frozen at issuance. Residency and First Time Jobseeker certificates
     * also keep the residency detail staff confirmed, and Residency records whether a verified voter
     * registration existed so its wording only calls the resident a registered voter when that was checked.
     *
     * @param  array{resident_name: string, address?: string, residing_since?: string|null, years_of_residency?: int|null}  $attributes
     * @return array<string, mixed>
     */
    public function snapshot(CertificateType $certificateType, ?Resident $resident, array $attributes, ?DocumentRequest $documentRequest): array
    {
        $snapshot = [
            'full_name' => $resident === null ? $attributes['resident_name'] : $resident->full_name,
            'first_name' => $resident?->first_name,
            'middle_name' => $resident?->middle_name,
            'last_name' => $resident?->last_name,
            'suffix' => $resident?->suffix,
            'address' => $resident === null ? ($documentRequest->address ?? $attributes['address'] ?? null) : $resident->address,
            'date_of_birth' => $resident?->date_of_birth?->format('Y-m-d'),
            'gender' => $resident?->gender,
            'civil_status' => $resident?->civil_status,
            'nationality' => $resident?->nationality,
        ];

        foreach ($certificateType->staffEnteredDetails() as $detail) {
            $snapshot[$detail] = $attributes[$detail] ?? null;
        }

        if ($certificateType === CertificateType::CertificateOfResidency) {
            $registration = $resident?->voterRegistration;
            $snapshot['voter_verified'] = $registration !== null && $registration->status === 'active' && $registration->integrity_valid;
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function ensureDetailsAreComplete(CertificateType $certificateType, array $snapshot, ?string $purpose, bool $hasPhoto, bool $linkedToResident): void
    {
        $missing = CertificateDocument::missingFor($certificateType, $snapshot, $purpose, $hasPhoto);

        if ($missing !== []) {
            $fromResidentRecord = array_intersect($missing, array_map(CertificateType::detailLabel(...), ['date_of_birth', 'gender', 'civil_status', 'nationality', 'photo']));

            throw CertificateIssuanceException::incompleteDetails($missing, match (true) {
                $fromResidentRecord !== [] && ! $linkedToResident => 'Select the linked resident record so these details come from Resident Records.',
                $fromResidentRecord !== [] => 'Update the resident record in Resident Records first.',
                default => null,
            });
        }

        $birthDate = filled($snapshot['date_of_birth'] ?? null) ? CarbonImmutable::parse($snapshot['date_of_birth']) : null;

        if (filled($snapshot['residing_since'] ?? null)) {
            $residingSince = CarbonImmutable::createFromFormat('!Y-m', $snapshot['residing_since']);

            if ($residingSince === null || $residingSince->greaterThan(today()->startOfMonth())) {
                throw CertificateIssuanceException::invalidDetail('Resident since cannot be later than this month.');
            }

            if ($birthDate !== null && $residingSince->lessThan($birthDate->startOfMonth())) {
                throw CertificateIssuanceException::invalidDetail('Resident since cannot be earlier than the resident\'s date of birth.');
            }
        }

        if (isset($snapshot['years_of_residency']) && $birthDate !== null) {
            $age = (int) floor($birthDate->diffInYears(today()));

            if ((int) $snapshot['years_of_residency'] > $age) {
                throw CertificateIssuanceException::invalidDetail("Years of residency cannot be more than the resident's age ({$age}).");
            }
        }
    }

    private function ensureResidentIsEligible(?Resident $resident, CertificateType $certificateType): void
    {
        if ($resident === null) {
            if (in_array($certificateType, [CertificateType::RegisteredVoterCertification, CertificateType::CertificateOfIndigency], true)) {
                throw CertificateIssuanceException::residentIsNotEligible('Select a verified resident record for this certificate.');
            }

            return;
        }

        $eligibility = $resident->certificateEligibility($certificateType);

        if (! $eligibility['eligible']) {
            throw CertificateIssuanceException::residentIsNotEligible($eligibility['reasons'][0]);
        }
    }
}
