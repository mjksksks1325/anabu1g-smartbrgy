<?php

namespace App\Actions;

use App\Models\Household;
use App\Models\Resident;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageHouseholds
{
    /**
     * A persistent singleton row serializes numbering, membership, head changes and
     * archiving in a common lock order, including households with no head yet.
     */
    private function lock(): void
    {
        DB::table('household_number_sequences')->where('id', 1)->lockForUpdate()->firstOrFail();
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Household
    {
        return DB::transaction(function () use ($attributes): Household {
            $this->lock();
            $number = $attributes['household_number'] ?? null;
            if ($number !== null && $number !== '') {
                if (Household::query()->where('household_number', $number)->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['household_number' => 'This household number is already assigned.']);
                }
                if (preg_match('/^HH-(\d{1,18})$/', $number, $matches) === 1) {
                    DB::table('household_number_sequences')->where('id', 1)->where('last_number', '<', (int) $matches[1])->update(['last_number' => (int) $matches[1]]);
                }
            } else {
                do {
                    DB::table('household_number_sequences')->where('id', 1)->increment('last_number');
                    $sequence = (int) DB::table('household_number_sequences')->where('id', 1)->value('last_number');
                    $number = 'HH-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
                } while (Household::query()->where('household_number', $number)->lockForUpdate()->exists());
            }
            $household = new Household(Arr::only($attributes, ['household_name', 'address', 'purok_id']));
            $household->household_number = $number;
            $household->save();

            return $household;
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function createWithMembers(array $attributes): Household
    {
        return DB::transaction(function () use ($attributes): Household {
            $this->lock();
            $household = $this->create($attributes);
            foreach ($attributes['members'] ?? [] as $member) {
                $resident = Resident::query()->whereKey($member['resident_id'])->lockForUpdate()->firstOrFail();
                $this->assign($resident, $household->id, $member['relationship_to_household_head'] ?? null, false);
            }
            if (! empty($attributes['household_head_resident_id'])) {
                $head = Resident::query()->whereKey($attributes['household_head_resident_id'])->lockForUpdate()->firstOrFail();
                $this->assign($head, $household->id, 'Head', true);
            }

            return $household->refresh();
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function saveResident(array $attributes, ?Resident $resident = null): Resident
    {
        return DB::transaction(function () use ($attributes, $resident): Resident {
            $this->lock();
            $resident = $resident ? Resident::query()->lockForUpdate()->findOrFail($resident->id) : new Resident;
            $resident->fill(Arr::except($attributes, ['confirm_duplicate', 'household_id', 'is_household_head', 'relationship_to_household_head', 'new_household']));
            $resident->save();

            if (isset($attributes['new_household'])) {
                if (! empty($attributes['household_id'])) {
                    throw ValidationException::withMessages(['household_id' => 'Select an existing household or create a new one, not both.']);
                }
                $attributes['household_id'] = $this->create($attributes['new_household'])->id;
            }

            if (array_key_exists('household_id', $attributes)) {
                $this->assign($resident, $attributes['household_id'] ? (int) $attributes['household_id'] : null,
                    $attributes['relationship_to_household_head'] ?? null, (bool) ($attributes['is_household_head'] ?? false));
            } elseif ($resident->status !== 'active' && $resident->is_household_head) {
                $this->assign($resident, $resident->household_id, $resident->relationship_to_household_head, false);
            } elseif (! empty($attributes['is_household_head']) || ! empty($attributes['relationship_to_household_head'])) {
                throw ValidationException::withMessages(['household_id' => 'Select a household before setting household information.']);
            }

            return $resident->refresh();
        }, 3);
    }

    public function assign(Resident $resident, ?int $householdId, ?string $relationship, bool $isHead): void
    {
        DB::transaction(function () use ($resident, $householdId, $relationship, $isHead): void {
            $this->lock();
            $resident = Resident::query()->withTrashed()->lockForUpdate()->findOrFail($resident->id);
            $household = $householdId ? Household::query()->lockForUpdate()->findOrFail($householdId) : null;

            if ($resident->trashed()) {
                throw ValidationException::withMessages(['resident_id' => 'Archived residents cannot be assigned to a household.']);
            }
            if ($isHead && (! $household || $resident->status !== 'active')) {
                throw ValidationException::withMessages(['is_household_head' => 'A household head must be an active member of a household.']);
            }
            if (! $household && $relationship) {
                throw ValidationException::withMessages(['household_id' => 'Select a household before setting a relationship.']);
            }

            Household::query()->where('household_head_resident_id', $resident->id)->update(['household_head_resident_id' => null]);
            if ($household && $isHead) {
                $previous = Resident::query()->where('household_id', $household->id)->where('is_household_head', true)->lockForUpdate()->get();
                foreach ($previous as $previousHead) {
                    $previousHead->forceFill(['is_household_head' => false, 'relationship_to_household_head' => null])->save();
                }
                $household->household_head_resident_id = $resident->id;
                $household->save();
            }

            $resident->forceFill([
                'household_id' => $household?->id,
                'relationship_to_household_head' => $household ? ($isHead ? 'Head' : ($relationship === 'Head' ? null : $relationship)) : null,
                'is_household_head' => $isHead,
            ])->save();
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function update(Household $household, array $attributes): Household
    {
        return DB::transaction(function () use ($household, $attributes): Household {
            $this->lock();
            $household = Household::query()->lockForUpdate()->findOrFail($household->id);
            $household->update(Arr::only($attributes, ['household_name', 'address', 'purok_id']));
            if (array_key_exists('household_head_resident_id', $attributes)) {
                $headId = $attributes['household_head_resident_id'];
                if ($headId !== null) {
                    $head = $household->members()->whereKey($headId)->lockForUpdate()->first();
                    if (! $head) {
                        throw ValidationException::withMessages(['household_head_resident_id' => 'The head must belong to this household.']);
                    }
                    $this->assign($head, $household->id, 'Head', true);
                } elseif ($household->household_head_resident_id) {
                    $head = Resident::query()->lockForUpdate()->find($household->household_head_resident_id);
                    if ($head) {
                        $this->assign($head, $household->id, null, false);
                    }
                }
            }

            return $household->refresh();
        }, 3);
    }

    public function archive(Resident $resident): void
    {
        DB::transaction(function () use ($resident): void {
            $this->lock();
            $resident = Resident::query()->lockForUpdate()->findOrFail($resident->id);
            if ($resident->is_household_head) {
                $this->assign($resident, $resident->household_id, null, false);
                $resident->refresh();
            }
            $resident->delete();
        }, 3);
    }

    public function remove(Household $household, Resident $resident): void
    {
        DB::transaction(function () use ($household, $resident): void {
            $this->lock();
            $resident = Resident::query()->lockForUpdate()->findOrFail($resident->id);
            abort_unless($resident->household_id === $household->id, 404);
            $this->assign($resident, null, null, false);
        }, 3);
    }
}
