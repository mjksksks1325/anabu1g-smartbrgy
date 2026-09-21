<?php

namespace App\Models;

use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property Carbon $occurred_at
 * @property Carbon|null $resolved_at
 * @property list<array{name: string, path: string, mime: string, size: int}>|null $attachments
 */
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'incident_type', 'occurred_at', 'location', 'complainant_name',
        'respondent_name', 'severity', 'details', 'status',
        'resolution_notes', 'attachments', 'reported_by', 'assigned_to',
        'resolved_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Incident $incident): void {
            $incident->incident_number ??= 'INC-'.now()->format('Y').'-'.Str::upper(Str::random(10));
        });
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'resolved_at' => 'datetime',
            'attachments' => 'array',
        ];
    }
}
