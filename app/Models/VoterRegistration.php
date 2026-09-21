<?php

namespace App\Models;

use Database\Factories\VoterRegistrationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $resident_id
 * @property string $voter_number
 * @property string $comelec_voter_number
 * @property string $comelec_voter_number_hash
 * @property string $precinct_number
 * @property string $cluster_number
 * @property Carbon $registration_date
 * @property string $status
 * @property int $version
 * @property string $integrity_hash
 * @property Resident $resident
 * @property-read string $masked_comelec_voter_number
 * @property-read bool $integrity_valid
 */
class VoterRegistration extends Model
{
    /** @use HasFactory<VoterRegistrationFactory> */
    use HasFactory;

    protected $fillable = [
        'resident_id', 'comelec_voter_number', 'comelec_voter_number_hash',
        'precinct_number', 'cluster_number', 'registration_date', 'status',
        'created_by', 'updated_by',
    ];

    protected $hidden = ['comelec_voter_number', 'comelec_voter_number_hash', 'integrity_hash'];

    protected $appends = ['masked_comelec_voter_number', 'integrity_valid'];

    protected static function booted(): void
    {
        static::creating(function (VoterRegistration $registration): void {
            $registration->voter_number ??= 'VTR-'.now()->format('Y').'-'.Str::upper(Str::random(10));
            $registration->version = 1;
            $registration->integrity_hash = $registration->calculateIntegrityHash();
        });

        static::updating(function (VoterRegistration $registration): void {
            $registration->version = max(1, ((int) $registration->getOriginal('version')) + 1);
            $registration->integrity_hash = $registration->calculateIntegrityHash();
        });
    }

    /** @return BelongsTo<Resident, $this> */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<VoterRegistrationAudit, $this> */
    public function audits(): HasMany
    {
        return $this->hasMany(VoterRegistrationAudit::class);
    }

    public static function identifierHash(string $identifier): string
    {
        return hash_hmac('sha256', Str::upper($identifier), self::integrityKey());
    }

    public function getMaskedComelecVoterNumberAttribute(): string
    {
        $identifier = (string) $this->comelec_voter_number;

        return str_repeat('•', max(mb_strlen($identifier) - 4, 4)).Str::substr($identifier, -4);
    }

    public function getIntegrityValidAttribute(): bool
    {
        return hash_equals($this->integrity_hash, $this->calculateIntegrityHash());
    }

    private function calculateIntegrityHash(): string
    {
        $payload = implode('|', [
            (string) $this->resident_id,
            (string) $this->voter_number,
            (string) $this->comelec_voter_number_hash,
            (string) $this->precinct_number,
            (string) $this->cluster_number,
            $this->registration_date->format('Y-m-d'),
            (string) $this->status,
            (string) $this->version,
        ]);

        return hash_hmac('sha256', $payload, self::integrityKey());
    }

    private static function integrityKey(): string
    {
        return hash('sha256', (string) config('app.key'));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'comelec_voter_number' => 'encrypted',
            'registration_date' => 'date',
            'version' => 'integer',
        ];
    }
}
