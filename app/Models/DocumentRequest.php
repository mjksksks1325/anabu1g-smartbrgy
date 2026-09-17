<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DocumentRequest extends Model
{
    protected $fillable = [
        'reference_code',
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
        'status',
        'remarks',
    ];

    /**
     * @return HasOne<IssuedCertificate, $this>
     */
    public function issuedCertificate(): HasOne
    {
        return $this->hasOne(IssuedCertificate::class);
    }
}
