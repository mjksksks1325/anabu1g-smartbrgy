<?php

namespace App;

enum CertificateType: string
{
    case BarangayClearance = 'Barangay Clearance';
    case CertificateOfResidency = 'Certificate of Residency';
    case CertificateOfIndigency = 'Certificate of Indigency';
    case RegisteredVoterCertification = 'Registered Voter Certification';
    case BarangayId = 'Barangay ID';
    case FirstTimeJobseeker = 'First Time Jobseeker';
    case BusinessClearance = 'Business Clearance';

    public function portalCode(): string
    {
        return match ($this) {
            self::RegisteredVoterCertification => 'RVC', self::BarangayClearance => 'BC', self::CertificateOfResidency => 'CR',
            self::CertificateOfIndigency => 'CI', self::BarangayId => 'BID',
            self::FirstTimeJobseeker => 'CTFJ', self::BusinessClearance => 'BBC',
        };
    }

    public function fee(): int
    {
        return match ($this) {
            self::RegisteredVoterCertification, self::BarangayClearance, self::CertificateOfResidency => 25,
            self::CertificateOfIndigency, self::FirstTimeJobseeker => 0,
            self::BarangayId => 100,
            self::BusinessClearance => 200,
        };
    }

    /**
     * Whether the barangay has confirmed this fee. Business Clearance (PHP 200) is still awaiting
     * confirmation, so every screen that shows it must say so.
     */
    public function feeConfirmed(): bool
    {
        return $this !== self::BusinessClearance;
    }

    /**
     * The one fee list every screen reads, keyed by certificate name.
     *
     * @return array<string, array{amount: int, confirmed: bool}>
     */
    public static function feeSchedule(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type): array => [
            $type->value => ['amount' => $type->fee(), 'confirmed' => $type->feeConfirmed()],
        ])->all();
    }

    /**
     * Barangay IDs and First Time Jobseeker certifications (RA 11261, as the official template states) last one year;
     * every other certificate lasts three months.
     */
    public function validityPeriodInMonths(): int
    {
        return in_array($this, [self::BarangayId, self::FirstTimeJobseeker], true) ? 12 : 3;
    }

    /**
     * The official Barangay Anabu I-G Word templates in public/certificates_format/ that this certificate's print layout
     * follows; empty when the barangay has not supplied one.
     *
     * @return list<string>
     */
    public function officialReferences(): array
    {
        return match ($this) {
            self::CertificateOfIndigency => ['Brgy. Indigency 2026.docx'],
            self::RegisteredVoterCertification => ['Brgy. Certificate-bonafide-registered 2026.docx'],
            self::CertificateOfResidency => ['Brgy. Certificate-Residency-Senior 2026.docx'],
            self::FirstTimeJobseeker => ['Brgy. Certificate First time job seeker.docx', 'Brgy.Certificate-First time job seeker- Oath of Undertaking 2026.docx'],
            self::BarangayClearance, self::BusinessClearance, self::BarangayId => [],
        };
    }

    /**
     * Details the printed certificate states, so they must be on record before it is issued or printed.
     * `photo` is the resident photo the wording "whose photo, signature and thumb mark appear herein" refers to.
     *
     * @return list<'full_name'|'address'|'date_of_birth'|'gender'|'civil_status'|'nationality'|'purpose'|'photo'|'residing_since'|'years_of_residency'>
     */
    public function requiredDetails(): array
    {
        return match ($this) {
            self::CertificateOfIndigency, self::RegisteredVoterCertification => ['full_name', 'address', 'date_of_birth', 'gender', 'civil_status', 'nationality', 'purpose', 'photo'],
            self::CertificateOfResidency => ['full_name', 'address', 'date_of_birth', 'civil_status', 'purpose', 'residing_since'],
            self::FirstTimeJobseeker => ['full_name', 'address', 'date_of_birth', 'years_of_residency'],
            self::BarangayClearance, self::BusinessClearance => ['full_name', 'address', 'purpose'],
            self::BarangayId => ['full_name'],
        };
    }

    /**
     * Details staff confirm at issuance because the resident record does not hold them.
     *
     * @return list<'residing_since'|'years_of_residency'>
     */
    public function staffEnteredDetails(): array
    {
        return array_values(array_intersect(['residing_since', 'years_of_residency'], $this->requiredDetails()));
    }

    /**
     * How staff screens name a required detail.
     */
    public static function detailLabel(string $detail): string
    {
        return match ($detail) {
            'full_name' => 'Full name',
            'address' => 'Address',
            'date_of_birth' => 'Date of birth',
            'gender' => 'Gender',
            'civil_status' => 'Civil status',
            'nationality' => 'Nationality',
            'purpose' => 'Purpose',
            'photo' => 'Resident photo',
            'residing_since' => 'Resident since (month and year)',
            'years_of_residency' => 'Years of residency in Anabu I-G',
            default => $detail,
        };
    }

    /**
     * The fee as residents read it in the portal.
     */
    public function feeLabel(): string
    {
        return $this->fee() === 0 ? 'Walang bayad' : 'PHP '.number_format($this->fee(), 2);
    }

    /**
     * @return view-string
     */
    public function printView(): string
    {
        return match ($this) {
            self::RegisteredVoterCertification => 'admin.certificates.registered-voter',
            self::BarangayClearance => 'admin.certificates.barangay-clearance',
            self::CertificateOfResidency => 'admin.certificates.residency',
            self::CertificateOfIndigency => 'admin.certificates.indigency',
            self::BarangayId => 'admin.certificates.barangay-id',
            self::FirstTimeJobseeker => 'admin.certificates.first-time-jobseeker',
            self::BusinessClearance => 'admin.certificates.business-clearance',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $certificateType): string => $certificateType->value,
            self::cases(),
        );
    }

    public static function tryFromLabel(string $label): ?self
    {
        $normalizedLabel = trim($label) === 'First Time Job Seeker'
            ? self::FirstTimeJobseeker->value
            : trim($label);

        return self::tryFrom($normalizedLabel);
    }
}
