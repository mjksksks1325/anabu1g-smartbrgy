<?php

namespace App\Actions;

use App\CertificateDocument;
use App\CertificateType;
use App\Models\DocumentRequest;
use App\Models\Resident;
use Illuminate\Support\Facades\Storage;

/**
 * What stands between a document request and its issuance, as IssueCertificate enforces it: disqualifications, staff
 * verifications still pending and details missing from the record. The preview and the Print & Release checklist both
 * read it, so staff see the same requirements in both places. It only reads: nothing is verified or filled in.
 */
class CheckCertificateReadiness
{
    /** Labels of the resident record details staff complete in Resident Records (CertificateType::detailLabel()). */
    private const RECORD_DETAILS = ['full_name', 'address', 'date_of_birth', 'gender', 'civil_status', 'nationality', 'photo'];

    public function __construct(private IssueCertificate $issueCertificate) {}

    /**
     * @param  array{residing_since?: string|null, years_of_residency?: int|null}  $staffDetails  Details staff enter at issuance.
     * @return array{type: CertificateType|null, resident: Resident|null, problems: list<string>, verifications: list<string>, reasons: list<string>, missing: list<string>, snapshot: array<string, mixed>, photo: string|null}
     */
    public function check(DocumentRequest $documentRequest, array $staffDetails = []): array
    {
        $type = CertificateType::tryFromLabel((string) $documentRequest->document_type);
        $resident = $documentRequest->resident;
        $result = ['type' => $type, 'resident' => $resident, 'problems' => [], 'verifications' => [], 'reasons' => [], 'missing' => [], 'snapshot' => [], 'photo' => null];

        if ($type === null) {
            return $result;
        }

        if ($resident === null && in_array($type, [CertificateType::RegisteredVoterCertification, CertificateType::CertificateOfIndigency], true)) {
            $result['problems'] = $result['reasons'] = ['Select a verified resident record for this certificate.'];
        } elseif ($resident !== null) {
            $eligibility = $resident->certificateEligibility($type);
            $result['reasons'] = $eligibility['eligible'] ? [] : $eligibility['reasons'];
            // Only pending verifications allow a draft preview; any disqualification blocks it, listing every reason.
            if ($resident->awaitsOnlyVerification($type)) {
                $result['verifications'] = $result['reasons'];
            } else {
                $result['problems'] = $result['reasons'];
            }
        }

        $result['snapshot'] = $this->issueCertificate->snapshot($type, $resident, [
            'resident_name' => $documentRequest->full_name,
            'residing_since' => $staffDetails['residing_since'] ?? null,
            'years_of_residency' => isset($staffDetails['years_of_residency']) ? (int) $staffDetails['years_of_residency'] : null,
        ], $documentRequest);
        $disk = Storage::disk('local');
        $result['photo'] = $resident?->photo_path !== null && $disk->exists($resident->photo_path) ? $resident->photo_path : null;
        $result['missing'] = CertificateDocument::missingFor($type, $result['snapshot'], $documentRequest->purpose, $result['photo'] !== null);

        return $result;
    }

    /**
     * The Print & Release checklist: one item per missing detail, pending verification or disqualification, each saying
     * where authorized staff complete it. Details staff type into the dialog itself (residency) are left to the dialog.
     *
     * @return array{ready: bool, resident: array{id: int, resident_number: string}|null, items: list<array{kind: 'missing'|'verification'|'blocked', message: string, action: 'resident'|'indigency'|'voters'|null, field: string|null}>}
     */
    public function checklist(DocumentRequest $documentRequest): array
    {
        $readiness = $this->check($documentRequest);
        $type = $readiness['type'];
        $resident = $readiness['resident'];
        $items = [];

        if ($type === null) {
            $items[] = ['kind' => 'blocked', 'message' => 'This document type has no printed layout in Smart Barangay, so it cannot be issued here.', 'action' => null, 'field' => null];
        }

        foreach ($readiness['reasons'] as $reason) {
            $items[] = match ($reason) {
                Resident::INDIGENCY_NOT_VERIFIED => ['kind' => 'verification', 'message' => $reason, 'action' => 'indigency', 'field' => 'is_verified_indigent'],
                Resident::VOTER_REGISTRATION_NOT_VERIFIED => ['kind' => 'verification', 'message' => $reason, 'action' => 'voters', 'field' => null],
                default => ['kind' => 'blocked', 'message' => $reason, 'action' => null, 'field' => null],
            };
        }

        $staffEntered = array_map(CertificateType::detailLabel(...), $type?->staffEnteredDetails() ?? []);
        $recordLabels = array_combine(array_map(CertificateType::detailLabel(...), self::RECORD_DETAILS), self::RECORD_DETAILS);
        foreach (array_diff($readiness['missing'], $staffEntered) as $label) {
            $field = $recordLabels[$label] ?? null;
            $items[] = ['kind' => 'missing', 'message' => $label, 'action' => $field !== null && $resident !== null ? 'resident' : null, 'field' => $field];
        }

        return [
            'ready' => $items === [],
            'resident' => $resident === null ? null : ['id' => $resident->id, 'resident_number' => (string) $resident->resident_number],
            'items' => $items,
        ];
    }
}
