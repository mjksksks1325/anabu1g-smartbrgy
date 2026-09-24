<?php

namespace App;

enum CertificateType: string
{
    case BarangayClearance = 'Barangay Clearance';
    case CertificateOfResidency = 'Certificate of Residency';
    case CertificateOfIndigency = 'Certificate of Indigency';
    case BarangayId = 'Barangay ID';
    case FirstTimeJobseeker = 'First Time Jobseeker';
    case BusinessClearance = 'Business Clearance';

    public function portalCode(): string
    {
        return match ($this) {
            self::BarangayClearance => 'BC', self::CertificateOfResidency => 'CR',
            self::CertificateOfIndigency => 'CI', self::BarangayId => 'BID',
            self::FirstTimeJobseeker => 'CTFJ', self::BusinessClearance => 'BBC',
        };
    }

    public function fee(): int
    {
        return match ($this) {
            self::BarangayClearance, self::CertificateOfResidency => 50,
            self::CertificateOfIndigency, self::FirstTimeJobseeker => 0,
            self::BarangayId => 100,
            self::BusinessClearance => 200,
        };
    }

    /**
     * @return view-string
     */
    public function printView(): string
    {
        return match ($this) {
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
