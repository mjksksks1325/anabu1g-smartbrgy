<?php

namespace App\Actions;

use App\AdditionalCertificateType;
use App\CertificateDocument;
use App\Exceptions\CertificateIssuanceException;
use App\Models\CertificateEvidence;
use App\Models\IssuedCertificate;
use App\Models\Resident;
use F9WebLtd\QrCode\Generator;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Builds and issues the staff-only certificates (AdditionalCertificateType). The same details back the preview and
 * the issued certificate, which is refused until the barangay has approved the certificate's fee.
 */
class IssueAdditionalCertificate
{
    public const EVIDENCE_LABEL = 'Community service photographs';

    public function __construct(
        private readonly FilesystemManager $filesystem,
        private readonly Generator $qrCode,
    ) {}

    /**
     * The certificate as it would be issued now (unsaved), with what still stands in the way.
     *
     * @param  array<string, mixed>  $details
     * @return array{certificate: IssuedCertificate, problems: list<string>, missing: list<string>, photo: string|null, evidence: list<string>}
     */
    public function prepare(AdditionalCertificateType $type, Resident $resident, ?Resident $partner, array $details): array
    {
        $issuedAt = now();
        $photo = $resident->photo_path !== null && $this->filesystem->disk('local')->exists($resident->photo_path) ? $resident->photo_path : null;
        $snapshot = [
            'full_name' => $resident->full_name, 'first_name' => $resident->first_name, 'middle_name' => $resident->middle_name,
            'last_name' => $resident->last_name, 'suffix' => $resident->suffix, 'address' => $resident->address,
            'date_of_birth' => $resident->date_of_birth->format('Y-m-d'), 'gender' => $resident->gender,
            'civil_status' => $resident->civil_status, 'nationality' => $resident->nationality,
            'details' => $details,
        ];

        if ($type->needsPartner() && $partner !== null) {
            $snapshot['partner'] = ['resident_id' => $partner->id, 'full_name' => $partner->full_name, 'gender' => $partner->gender, 'date_of_birth' => $partner->date_of_birth->format('Y-m-d'), 'nationality' => $partner->nationality];
        }

        $labels = ['address' => 'Address', 'date_of_birth' => 'Date of birth', 'gender' => 'Gender', 'civil_status' => 'Civil status', 'nationality' => 'Nationality'];
        $missing = array_values(array_map(
            fn (string $detail): string => $labels[$detail],
            array_filter($type->requiredResidentDetails(), fn (string $detail): bool => blank($snapshot[$detail] ?? null)),
        ));
        if ($type->requiresPhoto() && $photo === null) {
            $missing[] = 'Resident photo';
        }
        $evidence = $type->acceptsEvidence()
            ? array_values(array_map(strval(...), CertificateEvidence::query()->draftFor($resident, $type->value)->pluck('uuid')->all()))
            : [];
        if ($type->acceptsEvidence()) {
            $snapshot['evidence'] = $evidence;
            if ($evidence === []) {
                $missing[] = self::EVIDENCE_LABEL;
            }
        }

        $certificate = new IssuedCertificate([
            'resident_id' => $resident->id,
            'certificate_type' => $type->value,
            'template_version' => CertificateDocument::TEMPLATE_VERSION,
            'resident_name' => $resident->full_name,
            'purpose' => $details['purpose'] ?? null,
            'amount_paid' => $type->fee() ?? 0,
            'issued_at' => $issuedAt,
            'expires_on' => $issuedAt->copy()->addMonthsNoOverflow($type->validityPeriodInMonths())->toDateString(),
            'resident_snapshot' => $snapshot,
            'photo_path' => $photo,
        ]);

        return [
            'certificate' => $certificate,
            'problems' => $type->eligibilityProblems($resident, $partner, $details, $issuedAt),
            'missing' => $missing,
            'photo' => $photo,
            'evidence' => $evidence,
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public function issue(AdditionalCertificateType $type, int $residentId, ?int $partnerId, array $details, string $issuedBy): IssuedCertificate
    {
        if (! $type->issuanceEnabled()) {
            throw CertificateIssuanceException::notApproved($type->value);
        }

        $qrStoragePath = null;
        $photoStoragePath = null;

        try {
            return DB::transaction(function () use ($type, $residentId, $partnerId, $details, $issuedBy, &$qrStoragePath, &$photoStoragePath): IssuedCertificate {
                $resident = Resident::query()->lockForUpdate()->findOrFail($residentId);
                $partner = $partnerId === null ? null : Resident::query()->find($partnerId);
                ['certificate' => $certificate, 'problems' => $problems, 'missing' => $missing, 'photo' => $photo, 'evidence' => $evidence] = $this->prepare($type, $resident, $partner, $details);

                if ($problems !== []) {
                    throw CertificateIssuanceException::residentIsNotEligible($problems[0]);
                }
                if ($missing !== []) {
                    throw CertificateIssuanceException::incompleteDetails($missing, $missing === [self::EVIDENCE_LABEL] ? 'Upload at least one community service photograph.' : 'Update the resident record in Resident Records first.');
                }

                $certificateNumber = 'CERT-'.now()->format('Y').'-'.Str::upper(Str::random(8));
                $verificationCode = Str::upper(Str::random(16));
                $qrStoragePath = 'qrcodes/'.$certificateNumber.'.svg';
                $qrSvg = (string) $this->qrCode->format('svg')->size(300)->generate(route('certificate.verify', ['code' => $verificationCode]));
                if (! $this->filesystem->disk('public')->put($qrStoragePath, $qrSvg)) {
                    throw CertificateIssuanceException::qrCodeCouldNotBeSaved();
                }

                if ($photo !== null) {
                    $photoStoragePath = 'certificate-photos/'.$certificateNumber.'.'.pathinfo($photo, PATHINFO_EXTENSION);
                    if (! $this->filesystem->disk('local')->copy($photo, $photoStoragePath)) {
                        throw new \RuntimeException('Certificate photo could not be saved.');
                    }
                }

                $certificate->fill([
                    'certificate_number' => $certificateNumber,
                    'verification_code' => $verificationCode,
                    'qr_code_path' => '/storage/'.$qrStoragePath,
                    'photo_path' => $photoStoragePath,
                    'issued_by' => $issuedBy,
                ])->save();

                if ($evidence !== []) {
                    $linked = CertificateEvidence::query()->whereIn('uuid', $evidence)->whereNull('issued_certificate_id')
                        ->where('resident_id', $resident->id)->update(['issued_certificate_id' => $certificate->id]);
                    if ($linked !== count($evidence)) {
                        throw CertificateIssuanceException::invalidDetail('The evidence photographs changed while issuing. Review them and try again.');
                    }
                }

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
}
