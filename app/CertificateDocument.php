<?php

namespace App;

use App\Models\IssuedCertificate;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use NumberFormatter;

/**
 * What a printed certificate says, read only from the details recorded when it was issued
 * (`resident_snapshot`), so later edits to the resident record never change a printed document.
 */
final class CertificateDocument
{
    /** Stored on certificates issued with the official 2026 templates; reprints of older ones use their own wording. */
    public const TEMPLATE_VERSION = '2026.11';

    /**
     * @param  bool  $isPreview  An unsaved certificate shown to staff before issuance: no QR, marked as not valid.
     * @param  string|null  $previewPhotoUrl  The resident photo a preview shows (an issued certificate shows its own copy).
     */
    public function __construct(
        public readonly IssuedCertificate $certificate,
        public readonly bool $isPreview = false,
        private readonly ?string $previewPhotoUrl = null,
    ) {}

    public function type(): ?CertificateType
    {
        return CertificateType::tryFromLabel((string) $this->certificate->certificate_type);
    }

    /**
     * Labels of the details this certificate's template states but the record lacks. A certificate with
     * missing details is not printed.
     *
     * @return list<string>
     */
    public function missingDetails(): array
    {
        $type = $this->type();

        if ($type === null) {
            return [];
        }

        $missing = self::missingFor($type, $this->snapshot(), $this->certificate->purpose, $this->certificate->photo_path !== null);

        return $this->certificate->issued_at === null ? [...$missing, 'Issue date'] : $missing;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<string>
     */
    public static function missingFor(CertificateType $type, array $snapshot, ?string $purpose, bool $hasPhoto): array
    {
        $missing = array_filter($type->requiredDetails(), fn (string $detail): bool => ! match ($detail) {
            'photo' => $hasPhoto,
            'purpose' => filled($purpose),
            default => filled($snapshot[$detail] ?? null),
        });

        return array_values(array_map(CertificateType::detailLabel(...), $missing));
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return ($this->certificate->resident_snapshot ?? []) + ['full_name' => $this->certificate->resident_name];
    }

    public function fullName(): string
    {
        return (string) ($this->snapshot()['full_name'] ?: $this->certificate->resident_name);
    }

    /**
     * The name over the resident's signature line, written as the templates do: first name, middle initial,
     * last name and suffix ("GRESIL Y. TAGARAO"). Falls back to the full name when the parts were not recorded.
     */
    public function signatureName(): string
    {
        $snapshot = $this->snapshot();

        if (blank($snapshot['first_name'] ?? null) || blank($snapshot['last_name'] ?? null)) {
            return $this->fullName();
        }

        $middleInitial = filled($snapshot['middle_name'] ?? null) ? Str::upper(Str::substr((string) $snapshot['middle_name'], 0, 1)).'.' : null;

        return collect([$snapshot['first_name'], $middleInitial, $snapshot['last_name'], $snapshot['suffix'] ?? null])->filter()->implode(' ');
    }

    public function address(): string
    {
        return (string) ($this->snapshot()['address'] ?? '');
    }

    /**
     * Whether the recorded address already names the barangay. The inline templates ("a resident of <address>,
     * Barangay Anabu I-G, City of Imus, Cavite") add the barangay only when it does not, so it is never printed twice.
     */
    public function addressNamesBarangay(): bool
    {
        return Str::contains($this->address(), 'Anabu', ignoreCase: true);
    }

    public function birthDate(): ?CarbonImmutable
    {
        $date = $this->snapshot()['date_of_birth'] ?? null;

        return filled($date) ? CarbonImmutable::parse((string) $date) : null;
    }

    public function ageAtIssue(): ?int
    {
        $birthDate = $this->birthDate();
        $issuedAt = $this->issuedAt();

        return $birthDate === null || $issuedAt === null ? null : (int) floor($birthDate->diffInYears($issuedAt->copy()->startOfDay()));
    }

    public function detail(string $key): string
    {
        return (string) ($this->snapshot()[$key] ?? '');
    }

    /** A certificate-specific detail staff entered at issuance (staff-only certificates). */
    public function extra(string $key): mixed
    {
        return ($this->snapshot()['details'] ?? [])[$key] ?? null;
    }

    /** @return array{full_name?: string, gender?: string|null, date_of_birth?: string|null, nationality?: string|null} */
    public function partner(): array
    {
        return $this->snapshot()['partner'] ?? [];
    }

    /** "Mr." or "Ms." from a recorded gender, as the templates' honorific blanks are filled; "Mr./Ms." when unknown. */
    public static function honorific(?string $gender): string
    {
        return match (strtolower((string) $gender)) {
            'male' => 'Mr.',
            'female' => 'Ms.',
            default => 'Mr./Ms.',
        };
    }

    /** "Ten" for 10, as the templates spell numbers out beside the figures. */
    public static function numberInWords(int $number): string
    {
        return ucfirst((string) (new NumberFormatter('en', NumberFormatter::SPELLOUT))->format($number));
    }

    public function purpose(): string
    {
        return (string) $this->certificate->purpose;
    }

    public function issuedAt(): ?CarbonInterface
    {
        return $this->certificate->issued_at;
    }

    public function validUntil(): ?CarbonInterface
    {
        return $this->certificate->validUntil();
    }

    public function residingSince(): ?CarbonImmutable
    {
        $month = $this->snapshot()['residing_since'] ?? null;

        return filled($month) ? CarbonImmutable::createFromFormat('!Y-m', (string) $month) ?: null : null;
    }

    public function yearsOfResidency(): ?int
    {
        $years = $this->snapshot()['years_of_residency'] ?? null;

        return is_numeric($years) ? (int) $years : null;
    }

    /**
     * Whether an active, untampered voter registration was on record at issuance. Only then may a certificate
     * without its own voter requirement (Residency) call the resident a registered voter.
     */
    public function voterVerified(): bool
    {
        return ($this->snapshot()['voter_verified'] ?? false) === true;
    }

    public function photoUrl(): ?string
    {
        if ($this->isPreview) {
            return $this->previewPhotoUrl;
        }

        return $this->certificate->photo_path !== null && $this->certificate->exists
            ? route('staff.issued-certificates.photo', $this->certificate)
            : null;
    }

    public function qrUrl(): ?string
    {
        return $this->certificate->qr_code_path ? asset($this->certificate->qr_code_path) : null;
    }

    /**
     * @return array{name: string, title: string}
     */
    public static function official(string $key): array
    {
        /** @var array{name: string, title: string} */
        return config("certificates.officials.{$key}");
    }
}
