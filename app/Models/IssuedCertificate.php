<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $issued_at
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
    ];

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
            'amount_paid' => 'decimal:2',
        ];
    }
}
