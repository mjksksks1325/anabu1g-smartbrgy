<?php

namespace App\Actions;

use App\Models\Resident;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResidentIdentity
{
    /** @param array<string, mixed> $attributes */
    public function rejectDuplicate(
        array $attributes,
        bool $duplicateConfirmed,
        ?Resident $ignoredResident = null,
        bool $lock = false,
    ): void {
        $matches = $this->matches($attributes, $ignoredResident, $lock);

        $exactMatch = $matches->first(fn (Resident $resident): bool => $this->normalizedName($resident->middle_name) === $this->normalizedName($attributes['middle_name'] ?? null)
            && $this->normalizedName($resident->suffix) === $this->normalizedName($attributes['suffix'] ?? null));

        if ($exactMatch !== null) {
            throw ValidationException::withMessages([
                'first_name' => "An exact resident record already exists: {$exactMatch->full_name} ({$exactMatch->resident_number}). Review the existing record".($exactMatch->trashed() ? ' or restore it.' : '.'),
            ]);
        }

        $possibleMatch = $matches->first();
        if ($possibleMatch !== null && ! $duplicateConfirmed) {
            throw ValidationException::withMessages([
                'duplicate' => "Possible duplicate: {$possibleMatch->full_name} ({$possibleMatch->resident_number}). Review the existing record or confirm this is a separate resident.",
            ]);
        }
    }

    /** @param array<string, mixed> $attributes */
    public function residentIdentityLockKey(array $attributes): string
    {
        return 'resident-identity:'.hash('sha256', serialize([
            $this->normalizedName($attributes['first_name']),
            $this->normalizedName($attributes['last_name']),
            $attributes['date_of_birth'],
        ]));
    }

    private function normalizedName(?string $name): string
    {
        return Str::lower(Str::squish((string) $name));
    }

    /** @param array<string, mixed> $attributes
     * @return Collection<int, Resident>
     */
    public function matches(array $attributes, ?Resident $ignoredResident = null, bool $lock = false): Collection
    {
        return Resident::query()->withTrashed()
            ->whereDate('date_of_birth', $attributes['date_of_birth'])
            ->when($ignoredResident !== null, fn (Builder $query) => $query->whereKeyNot($ignoredResident->getKey()))
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())
            ->get()
            ->filter(fn (Resident $resident): bool => $this->normalizedName($resident->first_name) === $this->normalizedName($attributes['first_name'])
                && $this->normalizedName($resident->last_name) === $this->normalizedName($attributes['last_name']));
    }
}
