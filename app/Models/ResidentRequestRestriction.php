<?php

namespace App\Models;

use Database\Factories\ResidentRequestRestrictionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $ends_at
 * @property Carbon $starts_at
 * @property Carbon|null $reviewed_at
 */
class ResidentRequestRestriction extends Model
{
    /** @use HasFactory<ResidentRequestRestrictionFactory> */
    use HasFactory;

    public const MESSAGE = 'Your request is currently on hold and requires barangay review.';

    protected $fillable = ['resident_id', 'incident_id', 'restriction_type', 'affected_document_type', 'reason_category', 'internal_reason', 'resident_visible_reason', 'starts_at', 'ends_at', 'next_review_at', 'status', 'reviewed_by', 'reviewed_at', 'created_by', 'lifted_by', 'lifted_at', 'lift_reason'];

    /** @return BelongsTo<Resident, $this> */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /** @return BelongsTo<Incident, $this> */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'next_review_at' => 'datetime', 'reviewed_at' => 'datetime', 'lifted_at' => 'datetime'];
    }
}
