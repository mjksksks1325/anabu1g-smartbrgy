<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonInterface|null $issued_at
 * @property CarbonInterface|null $expires_on
 * @property array<string, string|null>|null $resident_snapshot
 * @property string|null $photo_path
 */
class IssuedCertificate extends Model
{
    protected $fillable = [
        'document_request_id',
        'resident_id',
        'certificate_number',
        'verification_code',
        'certificate_type',
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

    protected $hidden = ['resident_snapshot', 'photo_path'];

    public function validUntil(): ?CarbonInterface
    {
        $threeMonthLimit = $this->issued_at?->copy()->addMonthsNoOverflow(3)->startOfDay();

        if ($threeMonthLimit === null) {
            return $this->expires_on?->copy();
        }

        if ($this->expires_on === null || $threeMonthLimit->lt($this->expires_on)) {
            return $threeMonthLimit;
        }

        return $this->expires_on->copy();
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
            'resident_snapshot' => 'array',
            'amount_paid' => 'decimal:2',
        ];
    }
}
