<?php

namespace App\Models;

use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    public const STATUSES = ['open', 'under_review', 'referred', 'resolved', 'closed', 'pending', 'under_investigation', 'dismissed'];

    public const SUBMISSION_CATEGORIES = [
        'Disturbance / altercation' => 'Reported disturbance / altercation',
        'Noise Complaint' => 'Noise complaint',
        'Theft' => 'Reported theft',
        'Vandalism' => 'Reported property damage',
        'Accident' => 'Accident',
        'Suspicious activity' => 'Reported suspicious activity',
        'Property Dispute' => 'Reported property dispute',
        'Physical Assault' => 'Reported physical assault',
        'Iba pa' => 'Other ordinary incident (Iba pa)',
    ];

    /** @use HasFactory<IncidentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'incident_type', 'occurred_at', 'location', 'complainant_name',
        'respondent_name', 'severity', 'details', 'status',
        'resolution_notes', 'attachments', 'reported_by', 'assigned_to',
        'is_sensitive', 'resolved_at', 'complainant_resident_id', 'respondent_resident_id', 'updated_by', 'remarks',
    ];

    /** @return HasMany<BarangayProtectionOrder, $this> */
    public function protectionOrders(): HasMany
    {
        return $this->hasMany(BarangayProtectionOrder::class);
    }

    public static function sensitiveType(string $type): bool
    {
        return preg_match('/vawc|bpo|violence against women|domestic violence/i', $type) === 1;
    }

    public function isRestricted(): bool
    {
        return (bool) $this->is_sensitive || self::sensitiveType($this->incident_type) || $this->protectionOrders()->exists();
    }

    /** @param Builder<Incident> $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $restricted = function (Builder $q): void {
            $q->where('is_sensitive', true)->orWhereRaw("LOWER(incident_type) LIKE '%vawc%'")
                ->orWhereRaw("LOWER(incident_type) LIKE '%bpo%'")
                ->orWhereRaw("LOWER(incident_type) LIKE '%violence against women%'")
                ->orWhereRaw("LOWER(incident_type) LIKE '%domestic violence%'")->orWhereHas('protectionOrders');
        };
        if (! $user->hasPermission('vawc.view')) {
            $query->whereNot($restricted);
        }
        if (! $user->hasPermission('incidents.view')) {
            $query->where($restricted);
        }
    }

    protected static function booted(): void
    {
        static::creating(function (Incident $incident): void {
            $incident->incident_number ??= 'INC-'.now()->format('Y').'-'.Str::ulid();
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

    /** @return HasMany<IncidentEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(IncidentEvent::class);
    }

    /** @return BelongsTo<Resident, $this> */
    public function complainant(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'complainant_resident_id');
    }

    /** @return BelongsTo<Resident, $this> */
    public function respondent(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'respondent_resident_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_sensitive' => 'boolean',
            'occurred_at' => 'datetime',
            'resolved_at' => 'datetime',
            'attachments' => 'array',
        ];
    }
}
