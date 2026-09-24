<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DocumentRequest extends Model
{
    protected $fillable = [
        'reference_code',
        'resident_id',
        'source',
        'document_type',
        'full_name',
        'date_of_birth',
        'address',
        'contact_number',
        'email',
        'purpose',
        'business_name',
        'attachment_path',
        'private_attachment_path',
        'status',
        'remarks',
        'rejection_reason',
        'rejected_at',
        'rejected_by',
    ];

    protected $hidden = ['private_attachment_path'];

    public function getAttachmentPathAttribute(?string $value): ?string
    {
        return $this->private_attachment_path
            ? route('admin.document-requests.attachment', $this)
            : $value;
    }

    /**
     * @return HasOne<IssuedCertificate, $this>
     */
    public function issuedCertificate(): HasOne
    {
        return $this->hasOne(IssuedCertificate::class);
    }

    /** @return BelongsTo<Resident, $this> */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /** @return BelongsTo<User, $this> */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rejected_at' => 'datetime',
        ];
    }
}
