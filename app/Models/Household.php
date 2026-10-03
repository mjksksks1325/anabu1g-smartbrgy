<?php

namespace App\Models;

use Database\Factories\HouseholdFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property string $household_number
 * @property string|null $household_name
 * @property string $address
 * @property int|null $purok_id
 * @property int|null $household_head_resident_id
 * @property-read Resident|null $head
 */
class Household extends Model
{
    /** @use HasFactory<HouseholdFactory> */
    use HasFactory;

    protected $fillable = ['household_name', 'address', 'purok_id'];

    protected static function booted(): void
    {
        static::updating(function (Household $household): void {
            if ($household->isDirty('household_number')) {
                throw ValidationException::withMessages(['household_number' => 'The assigned household number cannot be changed.']);
            }
        });
    }

    /** @return BelongsTo<Resident, $this> */
    public function head(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'household_head_resident_id')->where('status', 'active');
    }

    /** @return HasMany<Resident, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(Resident::class)->orderBy('last_name')->orderBy('first_name')->orderBy('id');
    }

    /** @return BelongsTo<Purok, $this> */
    public function purok(): BelongsTo
    {
        return $this->belongsTo(Purok::class);
    }

    /** @return array{total_households: int, total_residents: int, male: int, female: int, registered_voters: int, average_household_size: float} */
    public static function demographics(): array
    {
        $householdCount = static::query()->count();

        return [
            'total_households' => $householdCount,
            'total_residents' => Resident::query()->count(),
            'male' => Resident::query()->where('gender', 'Male')->count(),
            'female' => Resident::query()->where('gender', 'Female')->count(),
            'registered_voters' => Resident::query()->whereHas('voterRegistration', fn ($query) => $query->where('status', 'active'))->count(),
            'average_household_size' => $householdCount > 0 ? round(Resident::query()->whereNotNull('household_id')->count() / $householdCount, 2) : 0.0,
        ];
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $this->loadMissing(['head', 'purok']);

        return [
            'id' => $this->id,
            'household_number' => $this->household_number,
            'household_name' => $this->household_name,
            'address' => $this->address,
            'purok_id' => $this->purok_id,
            'purok' => $this->purok?->name,
            'head' => $this->head ? ['id' => $this->head->id, 'full_name' => $this->head->full_name] : null,
            'household_size' => $this->members_count ?? $this->members()->count(),
        ];
    }

    /** @return array<string, mixed> */
    public function details(): array
    {
        $this->loadMissing(['members' => fn ($query) => $query->withExists(['voterRegistration as registered_voter' => fn ($query) => $query->where('status', 'active')])]);

        return [
            ...$this->summary(),
            'members' => $this->members->map(fn (Resident $resident): array => [
                'id' => $resident->id,
                'full_name' => $resident->full_name,
                'relationship_to_household_head' => $resident->relationship_to_household_head,
                'is_household_head' => $resident->is_household_head,
                'gender' => $resident->gender,
                'status' => $resident->status,
                'registered_voter' => (bool) $resident->getAttribute('registered_voter'),
            ])->all(),
        ];
    }
}
