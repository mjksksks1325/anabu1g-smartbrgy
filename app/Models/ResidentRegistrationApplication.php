<?php

namespace App\Models;

use Database\Factories\ResidentRegistrationApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property array<string, string|null> $details
 * @property string $status
 * @property string $reference
 * @property string $email
 * @property string $locale
 * @property string $photo_path
 */
class ResidentRegistrationApplication extends Model
{
    /** @use HasFactory<ResidentRegistrationApplicationFactory> */
    use HasFactory;

    protected $fillable = ['details', 'email'];

    protected $hidden = ['identity_key', 'email_key', 'photo_path'];

    /** @return BelongsTo<Resident, $this> */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    protected function casts(): array
    {
        return ['details' => 'array', 'reviewed_at' => 'datetime'];
    }

    /** @param array<string, string|null> $details */
    public static function fullName(array $details): string
    {
        return Str::squish(implode(' ', array_filter([$details['first_name'] ?? '', $details['middle_name'] ?? '', $details['last_name'] ?? '', $details['suffix'] ?? ''])));
    }

    /** @param array<string, string|null> $details */
    public static function identityKey(array $details): string
    {
        return hash('sha256', Str::lower(self::fullName($details)).'|'.$details['date_of_birth']);
    }

    /** @param array<string, string|null> $details */
    public static function matchesExistingResident(array $details): bool
    {
        return Resident::withTrashed()->whereDate('date_of_birth', $details['date_of_birth'])->get()
            ->contains(fn (Resident $resident): bool => Str::lower(Str::squish($resident->full_name)) === Str::lower(self::fullName($details)));
    }
}
