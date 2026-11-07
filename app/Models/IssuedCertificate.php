<?php

namespace App\Models;

use App\CertificateType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonInterface|null $issued_at
 * @property CarbonInterface|null $expires_on
 * @property array<string, string|null>|null $resident_snapshot
 * @property string|null $photo_path
 * @property string|null $template_version
 * @property CarbonInterface|null $revoked_at
 * @property int|null $revoked_by
 * @property string|null $revocation_reason
 */
class IssuedCertificate extends Model
{
    protected $fillable = [
        'document_request_id',
        'resident_id',
        'certificate_number',
        'verification_code',
        'certificate_type',
        'template_version',
        'resident_name',
        'purpose',
        'amount_paid',
        'issued_at',
        'issued_by',
        'qr_code_path',
        'resident_snapshot',
        'expires_on',
        'photo_path',
    ];

    protected $hidden = ['resident_snapshot', 'photo_path', 'revocation_reason'];

    /**
     * The last day the certificate is valid. Certificates issued before the 2026 templates keep the rule in force
     * when they were issued (one year for Barangay IDs, three months for everything else).
     */
    public function validUntil(): ?CarbonInterface
    {
        $certificateType = CertificateType::tryFromLabel((string) $this->certificate_type);
        $months = $this->template_version === null
            ? ($certificateType === CertificateType::BarangayId ? 12 : 3)
            : ($certificateType?->validityPeriodInMonths() ?? 3);
        $validityLimit = $this->issued_at?->copy()
            ->addMonthsNoOverflow($months)
            ->startOfDay();

        if ($validityLimit === null) {
            return $this->expires_on?->copy();
        }

        if ($this->expires_on === null || $validityLimit->lt($this->expires_on)) {
            return $validityLimit;
        }

        return $this->expires_on->copy();
    }

    /**
     * The holder's initials ("M. S. D. C."), which public QR verification shows instead of the full name so a verifier
     * can match the paper without the page exposing who holds the certificate.
     */
    public function holderInitials(): string
    {
        return collect(preg_split('/[\s,]+/u', trim((string) $this->resident_name), -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)).'.')
            ->implode(' ');
    }

    /**
     * Where the QR code file lives on the public disk ("qrcodes/CERT-….svg"), from the stored path in either of its
     * recorded forms ("/storage/qrcodes/…", "qrcodes/…" or an absolute URL); null when none is recorded.
     */
    public function qrStoragePath(): ?string
    {
        if ($this->qr_code_path === null || $this->qr_code_path === '') {
            return null;
        }

        $path = ltrim(parse_url($this->qr_code_path, PHP_URL_PATH) ?: $this->qr_code_path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return str_starts_with($path, 'qrcodes/') && ! str_contains($path, '..') ? $path : null;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /** @return BelongsTo<User, $this> */
    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isExpired(): bool
    {
        $validUntil = $this->validUntil();

        return $validUntil === null || $validUntil->lt(today());
    }

    /**
     * @return BelongsTo<DocumentRequest, $this>
     */
    public function documentRequest(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class);
    }

    /** @return BelongsTo<Resident, $this> */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_on' => 'date',
            'revoked_at' => 'datetime',
            'resident_snapshot' => 'array',
            'amount_paid' => 'decimal:2',
        ];
    }
}
