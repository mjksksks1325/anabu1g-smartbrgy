<?php

namespace App\Actions;

use App\Http\Requests\Admin\StoreResidentRequest;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportHouseholdProfiling
{
    public function __construct(private ResidentIdentity $identity, private ManageHouseholds $households) {}

    /** @return list<string> */
    public function fields(): array
    {
        return ['household_group', 'household_number', 'household_name', 'is_household_head', 'relationship_to_household_head', 'first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'gender', 'civil_status', 'purok', 'address', 'contact_number', 'residency_type'];
    }

    /** @param list<string> $headers
     * @return array<string, int>
     */
    public function mapping(array $headers): array
    {
        $aliases = ['family_id' => 'household_group', 'family_group' => 'household_group', 'household_id' => 'household_group', 'household_no' => 'household_number', 'household_head' => 'is_household_head', 'relationship' => 'relationship_to_household_head', 'birthdate' => 'date_of_birth', 'birth_date' => 'date_of_birth', 'sex' => 'gender', 'subdivision' => 'purok', 'contact' => 'contact_number'];
        $mapping = [];
        foreach ($headers as $index => $header) {
            $field = Str::snake(Str::lower(preg_replace('/[^a-zA-Z0-9]+/', ' ', $header) ?? ''));
            $field = $aliases[$field] ?? $field;
            if (in_array($field, $this->fields(), true)) {
                $mapping[$field] = $index;
            }
        }

        return $mapping;
    }

    /** @param array{headers: list<string>, rows: list<list<string>>} $raw
     * @param  array<string, int|null>  $mapping
     * @param  list<array<string, mixed>>  $edits
     * @return array<string, mixed>
     */
    public function preview(array $raw, array $mapping, ?string $purok, array $edits = []): array
    {
        $rows = [];
        $rules = Arr::only((new StoreResidentRequest)->rules(), ['first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'gender', 'civil_status', 'purok', 'address', 'contact_number', 'residency_type']);
        $rules += ['household_group' => ['nullable', 'string', 'max:100'], 'household_number' => ['nullable', 'string', 'max:100'], 'household_name' => ['nullable', 'string', 'max:150'], 'relationship_to_household_head' => ['nullable', 'string', 'max:100']];
        $edits = collect($edits)->keyBy('id');
        foreach ($raw['rows'] as $index => $values) {
            $fields = [];
            foreach ($this->fields() as $field) {
                $fields[$field] = isset($mapping[$field]) ? ($values[$mapping[$field]] ?? '') : '';
            }
            $edit = $edits->get($index, []);
            $fields = array_replace($fields, Arr::only($edit['fields'] ?? [], $this->fields()));
            foreach ($fields as $key => $value) {
                $fields[$key] = is_string($value) ? Str::squish($value) : '';
            }
            $fields['purok'] = $fields['purok'] ?: ($purok ?? '');
            $fields['household_group'] = $fields['household_group'] ?: $fields['household_number'];
            $fields['gender'] = match (Str::lower($fields['gender'])) {
                'm', 'male' => 'Male', 'f', 'female' => 'Female', default => $fields['gender']
            };
            $fields['contact_number'] = preg_replace('/[\s-]+/', '', $fields['contact_number']) ?: null;
            foreach (['middle_name', 'suffix'] as $nullable) {
                $fields[$nullable] = $fields[$nullable] ?: null;
            }
            try {
                $date = $fields['date_of_birth'];
                $fields['date_of_birth'] = is_numeric($date) && (float) $date > 0 && (float) $date < 100000
                    ? Carbon::parse('1899-12-30')->addDays((int) $date)->toDateString()
                    : ($date ? Carbon::parse($date)->toDateString() : '');
            } catch (\Throwable) {
                $fields['date_of_birth'] = '';
            }
            $validator = Validator::make($fields, $rules);
            $matches = collect();
            if (! $validator->errors()->hasAny(['first_name', 'last_name', 'date_of_birth'])) {
                $matches = $this->identity->matches($fields)->map(fn (Resident $resident): array => [
                    'id' => $resident->id, 'name' => $resident->full_name, 'resident_number' => $resident->resident_number, 'archived' => $resident->trashed(), 'can_head' => $resident->status === 'active',
                    'exact' => $this->normalized($resident->middle_name) === $this->normalized($fields['middle_name']) && $this->normalized($resident->suffix) === $this->normalized($fields['suffix']),
                ]);
            }
            $action = $edit['action'] ?? ($matches->isEmpty() ? 'create' : 'resolve');
            $errors = $action === 'create' ? $validator->errors()->all() : Validator::make($fields, Arr::only($rules, ['household_group', 'household_number', 'household_name', 'relationship_to_household_head', 'purok', 'address']))->errors()->all();
            $existingId = $edit['resident_id'] ?? null;
            if ($action === 'resolve') {
                $errors[] = 'Choose Link existing, Create as new after confirmation, or Skip.';
            }
            if ($action === 'link' && ! $matches->contains(fn (array $match): bool => $match['id'] === $existingId && ! $match['archived'])) {
                $errors[] = 'Select a matching active record. Restore archived residents in Resident Records before linking.';
            }
            if ($action === 'create' && $matches->contains('exact', true)) {
                $errors[] = 'An exact resident already exists. Link the record or skip this row.';
            } elseif ($action === 'create' && $matches->isNotEmpty() && ! ($edit['confirm_duplicate'] ?? false)) {
                $errors[] = 'Confirm that this possible duplicate is a separate resident.';
            }
            $head = $edit['is_head'] ?? (in_array(Str::lower($fields['is_household_head']), ['1', 'yes', 'true', 'head'], true) || Str::lower($fields['relationship_to_household_head']) === 'head');
            if ($head && $action === 'link' && $matches->contains(fn (array $match): bool => $match['id'] === $existingId && ! $match['can_head'])) {
                $errors[] = 'The household head must be an active resident.';
            }
            if ($action === 'skip') {
                $errors = [];
            }
            if ($action !== 'skip' && $fields['household_group'] === '') {
                $errors[] = 'Enter an explicit reviewed household group. Surname and address are never used for grouping.';
            }
            $rows[] = ['id' => $index, 'source_row' => $index + 2, 'fields' => $fields, 'is_head' => (bool) $head, 'action' => $action, 'resident_id' => $existingId, 'confirm_duplicate' => (bool) ($edit['confirm_duplicate'] ?? false), 'matches' => $matches->values()->all(), 'errors' => $errors];
        }
        $groups = [];
        foreach ($rows as $row) {
            if ($row['action'] === 'skip') {
                continue;
            }
            $group = $row['fields']['household_group'];
            $groups[$group][] = $row['id'];
        }
        $seen = [];
        $seenIdentities = [];
        foreach ($rows as &$row) {
            if ($row['action'] === 'skip') {
                continue;
            }
            $key = $row['action'] === 'link' ? 'resident:'.$row['resident_id'] : $this->identity->residentIdentityLockKey($row['fields']).':'.$this->normalized($row['fields']['middle_name']).':'.$this->normalized($row['fields']['suffix']);
            if (isset($seen[$key])) {
                $row['errors'][] = 'The same resident appears in another selected row. Skip the repeated row.';
            }
            $seen[$key] = true;
            $identityKey = $this->identity->residentIdentityLockKey($row['fields']);
            $row['file_duplicate'] = isset($seenIdentities[$identityKey]);
            if ($row['action'] === 'create' && $row['file_duplicate'] && ! $row['confirm_duplicate']) {
                $row['errors'][] = 'Another selected row has the same name and birth date. Confirm this is a separate resident or skip it.';
            }
            $seenIdentities[$identityKey] = true;
        }
        unset($row);
        $households = [];
        $seenNumbers = [];
        foreach ($groups as $group => $ids) {
            $members = array_map(fn (int $id): array => $rows[$id], $ids);
            $heads = array_values(array_filter($members, fn (array $row): bool => $row['is_head']));
            $head = $heads[0] ?? $members[0];
            $numbers = array_values(array_unique(array_filter(array_column(array_column($members, 'fields'), 'household_number'))));
            $puroks = array_values(array_unique(array_column(array_column($members, 'fields'), 'purok')));
            $names = array_values(array_unique(array_filter(array_column(array_column($members, 'fields'), 'household_name'), filled(...))));
            $groupErrors = [];
            if (count($names) > 1) {
                $groupErrors[] = 'Use one household name within each reviewed group.';
            }
            if (isset($numbers[0]) && isset($seenNumbers[$numbers[0]])) {
                $groupErrors[] = 'This household number appears under another group. Use one reviewed group for each household number.';
            }
            if (isset($numbers[0])) {
                $seenNumbers[$numbers[0]] = true;
            }
            if (count($heads) !== 1) {
                $groupErrors[] = 'Select exactly one head for this household.';
            }
            if (count($numbers) > 1 || count($puroks) !== 1) {
                $groupErrors[] = 'Household number and target purok must be consistent within the group.';
            }
            if ($head['fields']['address'] === '' || $head['fields']['purok'] === '') {
                $groupErrors[] = 'The household head row needs an address and target purok.';
            }
            $existing = isset($numbers[0]) ? Household::query()->where('household_number', $numbers[0])->first() : null;
            if ($existing && ($existing->address !== $head['fields']['address'] || $existing->purok?->name !== $head['fields']['purok'])) {
                $groupErrors[] = 'The existing household has a different address or purok. Review its record and target fields before linking.';
            }
            foreach ($rows as $index => $existingRow) {
                if (in_array($index, $ids, true)) {
                    $rows[$index] = array_replace($existingRow, ['errors' => [...$existingRow['errors'], ...$groupErrors]]);
                }
            }
            $households[] = ['group' => (string) $group, 'household_id' => $existing?->id, 'household_number' => $numbers[0] ?? null, 'household_name' => $names[0] ?? null, 'address' => $head['fields']['address'], 'purok' => $head['fields']['purok'], 'head' => trim($head['fields']['first_name'].' '.$head['fields']['last_name']), 'row_ids' => $ids];
        }

        return ['rows' => $rows, 'households' => $households, 'summary' => ['households' => count($households), 'residents' => count($rows), 'heads' => count(array_filter($rows, fn (array $row): bool => $row['is_head'] && $row['action'] !== 'skip')), 'duplicates' => count(array_filter($rows, fn (array $row): bool => $row['matches'] !== [] || ($row['file_duplicate'] ?? false))), 'invalid' => count(array_filter($rows, fn (array $row): bool => $row['errors'] !== [])), 'ready' => count(array_filter($rows, fn (array $row): bool => $row['errors'] === [] && $row['action'] !== 'skip'))]];
    }

    /** @param array<string, mixed> $preview
     * @return array{created: int, linked: int, skipped: int, households: int}
     */
    public function save(array $preview): array
    {
        $errors = [];
        foreach ($preview['rows'] as $row) {
            if ($row['errors'] !== []) {
                $errors['rows.'.$row['id']] = $row['errors'];
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $locks = [];
        $keys = [];
        foreach ($preview['rows'] as $row) {
            if ($row['action'] !== 'skip') {
                $keys[] = $this->identity->residentIdentityLockKey($row['fields']);
            }
        }
        $keys = array_unique($keys);
        sort($keys);
        try {
            foreach ($keys as $key) {
                $lock = Cache::lock($key, 120);
                $lock->block(5);
                $locks[] = $lock;
            }

            return DB::transaction(function () use ($preview): array {
                DB::table('household_number_sequences')->where('id', 1)->lockForUpdate()->firstOrFail();
                $counts = ['created' => 0, 'linked' => 0, 'skipped' => count(array_filter($preview['rows'], fn (array $row): bool => $row['action'] === 'skip')), 'households' => 0];
                $usedResidents = [];
                foreach ($preview['households'] as $group) {
                    $purok = Purok::query()->where('name', $group['purok'])->where('is_active', true)->lockForUpdate()->first();
                    if (! $purok) {
                        throw ValidationException::withMessages(['purok' => 'Select an existing active purok.']);
                    }
                    $household = $group['household_number'] ? Household::query()->where('household_number', $group['household_number'])->lockForUpdate()->first() : null;
                    if ($household && ($household->address !== $group['address'] || $household->purok_id !== $purok->id)) {
                        throw ValidationException::withMessages(['household' => 'The existing household changed. Review its address and purok again.']);
                    }
                    $household ??= $this->households->create(['household_number' => $group['household_number'], 'household_name' => $group['household_name'] ?? null, 'address' => $group['address'], 'purok_id' => $purok->id]);
                    $counts['households']++;
                    foreach ($group['row_ids'] as $id) {
                        $row = $preview['rows'][$id];
                        $fields = $row['fields'];
                        $matches = $this->identity->matches($fields, null, true);
                        if ($row['action'] === 'link') {
                            $resident = $matches->firstWhere('id', $row['resident_id']);
                            if (! $resident || $resident->trashed() || ($row['is_head'] && $resident->status !== 'active')) {
                                throw ValidationException::withMessages(['rows.'.$id => 'The linked record changed. Review the import again.']);
                            }
                            $counts['linked']++;
                        } else {
                            $this->identity->rejectDuplicate($fields, $row['confirm_duplicate'], null, true);
                            $resident = $this->households->saveResident(Arr::only($fields, ['first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'gender', 'civil_status', 'purok', 'address', 'contact_number', 'residency_type']));
                            $counts['created']++;
                        }
                        if (isset($usedResidents[$resident->id])) {
                            throw ValidationException::withMessages(['rows.'.$id => 'A resident can only be imported once. Skip the repeated row.']);
                        }
                        $usedResidents[$resident->id] = true;
                        $this->households->assign($resident, $household->id, $fields['relationship_to_household_head'] ?: null, $row['is_head']);
                    }
                }

                return $counts;
            }, 3);
        } finally {
            foreach (array_reverse($locks) as $lock) {
                $lock->release();
            }
        }
    }

    private function normalized(?string $value): string
    {
        return Str::lower(Str::squish((string) $value));
    }
}
