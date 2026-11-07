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
