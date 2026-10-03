<?php

namespace App\Models;

use Database\Factories\BarangayProtectionOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarangayProtectionOrder extends Model
{
    /** @use HasFactory<BarangayProtectionOrderFactory> */
    use HasFactory;

    protected $fillable = ['incident_id', 'protected_resident_id', 'respondent_resident_id', 'protected_person', 'respondent', 'issued_on', 'effective_on', 'ends_on', 'status', 'issuing_authority', 'internal_remarks', 'supporting_document_reference', 'created_by', 'updated_by'];

    /** @return BelongsTo<Incident, $this> */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['issued_on' => 'date:Y-m-d', 'effective_on' => 'date:Y-m-d', 'ends_on' => 'date:Y-m-d'];
    }
}
