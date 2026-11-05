<?php

namespace App\Actions;

use App\Http\Requests\Admin\StoreResidentRequest;
use App\Http\Requests\Admin\StoreVoterRegistrationRequest;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\VoterRegistration;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportHouseholdProfiling
{
    private const VOTER_FIELDS = ['comelec_voter_number', 'precinct_number', 'cluster_number', 'registration_date'];

    public function __construct(private ResidentIdentity $identity, private ManageHouseholds $households, private CreateVoterRegistration $voters) {}

    /** @return list<string> */
    public function fields(): array
    {
        return ['household_group', 'household_number', 'household_name', 'is_household_head', 'relationship_to_household_head', 'resident_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'gender', 'civil_status', 'purok', 'address', 'contact_number', 'residency_type', 'special_groups', 'is_registered_voter', ...self::VOTER_FIELDS];
    }

    /** @param list<string> $headers
     * @return array<string, int>
     */
    public function mapping(array $headers): array
    {
        $aliases = ['family_id' => 'household_group', 'family_group' => 'household_group', 'household_id' => 'household_group', 'household_no' => 'household_number', 'household_head' => 'is_household_head', 'relationship' => 'relationship_to_household_head', 'birthdate' => 'date_of_birth', 'birth_date' => 'date_of_birth', 'sex' => 'gender', 'subdivision' => 'purok', 'contact' => 'contact_number', 'resident_id' => 'resident_number', 'resident_no' => 'resident_number'];
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
        $rules = Arr::only((new StoreResidentRequest)->rules(), ['first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'gender', 'civil_status', 'purok', 'address', 'contact_number', 'residency_type', 'special_groups', 'special_groups.*']);
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
            $fields['contact_number'] = Resident::normalizeContactNumber($fields['contact_number']);
            $fields['comelec_voter_number'] = Str::upper(preg_replace('/\s+/u', '', $fields['comelec_voter_number']) ?? '');
            foreach (['middle_name', 'suffix'] as $nullable) {
                $fields[$nullable] = $fields[$nullable] ?: null;
            }
            try {
                $date = $fields['date_of_birth'];
                $fields['date_of_birth'] = is_numeric($date) && (float) $date > 0 && (float) $date < 100000
                    ? Carbon::parse('1899-12-30')->addDays((int) $date)->toDateString()
                    : ($date ? Carbon::parse($date)->toDateString() : '');
            } catch (\Throwable) {
                // Keep what was typed so staff can see and correct it; the date rule reports it as invalid.
            }
            $validator = Validator::make(array_replace($fields, [
                'special_groups' => $this->specialGroups($fields['special_groups']),
            ]), $rules, array_replace((new StoreResidentRequest)->messages(), [
                'special_groups.array' => 'Special groups must be a JSON array of group names, for example ["PWD","Solo Parent"].',
                'special_groups.*.in' => 'Special groups contains an unsupported group.',
            ]));
            $matches = collect();
            if (! $validator->errors()->hasAny(['first_name', 'last_name', 'date_of_birth'])) {
                $matches = $this->identity->matches($fields)->loadMissing('voterRegistration')->map(fn (Resident $resident): array => [
                    'id' => $resident->id, 'name' => $resident->full_name, 'resident_number' => $resident->resident_number, 'archived' => $resident->trashed(), 'can_head' => $resident->status === 'active',
                    'date_of_birth' => $resident->date_of_birth->toDateString(), 'registered_voter' => $resident->voterRegistration !== null,
                    'exact' => $this->normalized($resident->middle_name) === $this->normalized($fields['middle_name']) && $this->normalized($resident->suffix) === $this->normalized($fields['suffix']),
                ]);
            }
            $numberErrors = [];
            if ($fields['resident_number'] !== '') {
                $numbered = Resident::query()->withTrashed()->where('resident_number', $fields['resident_number'])->first();
                if (! $numbered) {
                    $numberErrors[] = "No resident has the resident number {$fields['resident_number']}. Correct it, or clear it for a new resident.";
                } elseif (! $this->isSameIdentity($numbered, $fields)) {
                    $numberErrors[] = "Resident number {$fields['resident_number']} belongs to {$numbered->full_name}. Check the name and date of birth in this row.";
                } else {
                    // The unique resident number confirms the match even when the middle name or suffix is written differently.
                    $matches = $matches->map(fn (array $match): array => [...$match, 'exact' => $match['exact'] || $match['id'] === $numbered->id]);
                }
            }
            $alreadyExists = $matches->contains('exact', true);
            $exactMatch = $matches->firstWhere('exact', true);
            $registeredVoter = $this->registeredVoter($fields['is_registered_voter']);
            $action = $edit['action'] ?? match (true) {
                $matches->isEmpty() => 'create',
                $registeredVoter === true && $exactMatch !== null && ! $exactMatch['archived'] && $exactMatch['can_head'] && ! $exactMatch['registered_voter'] => 'voter_only',
                $alreadyExists => 'skip',
                default => 'resolve',
            };
            $errors = $action === 'create' ? $validator->errors()->all() : Validator::make($fields, Arr::only($rules, ['household_group', 'household_number', 'household_name', 'relationship_to_household_head', 'purok', 'address']))->errors()->all();
            $existingId = $edit['resident_id'] ?? ($exactMatch['id'] ?? null);
            $existingMatch = $matches->firstWhere('id', $existingId);
            if ($action === 'voter_only') {
                $errors = [];
                if ($existingMatch === null || $existingMatch['archived'] || ! $existingMatch['can_head']) {
                    $errors[] = 'Select a matching active resident to add a voter record only.';
                }
                if ($registeredVoter !== true) {
                    $errors[] = 'Select Yes for registered voter before adding a voter record.';
                }
            }
            $willImportVoter = $registeredVoter === true && ! ($existingMatch['registered_voter'] ?? false) && $action !== 'skip';
            if ($action !== 'skip') {
                $errors = [...$errors, ...$this->voterErrors($fields, $existingMatch)];
            }
            $errors = [...$errors, ...$numberErrors];
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
            if ($action === 'voter_only') {
                $head = false;
            }
            if ($head && $action === 'link' && $matches->contains(fn (array $match): bool => $match['id'] === $existingId && ! $match['can_head'])) {
                $errors[] = 'The household head must be an active resident.';
            }
            if ($action === 'skip') {
                $errors = [];
            }
            if (! in_array($action, ['skip', 'voter_only'], true) && $fields['household_group'] === '') {
                $errors[] = 'Enter an explicit reviewed household group. Surname and address are never used for grouping.';
            }
            $rows[] = ['id' => $index, 'source_row' => $index + 2, 'fields' => $fields, 'is_head' => (bool) $head, 'action' => $action, 'resident_id' => $existingId, 'confirm_duplicate' => (bool) ($edit['confirm_duplicate'] ?? false), 'already_exists' => $alreadyExists, 'will_import_voter' => $willImportVoter, 'matches' => $matches->values()->all(), 'errors' => $errors];
        }
        $groups = [];
        foreach ($rows as $row) {
            if (in_array($row['action'], ['skip', 'voter_only'], true)) {
                continue;
            }
            $group = $row['fields']['household_group'];
            $groups[$group][] = $row['id'];
        }
        $seen = [];
        $seenIdentities = [];
        $seenVoterNumbers = [];
        foreach ($rows as &$row) {
            if ($row['action'] === 'skip') {
                continue;
            }
            $key = in_array($row['action'], ['link', 'voter_only'], true) ? 'resident:'.$row['resident_id'] : $this->identity->residentIdentityLockKey($row['fields']).':'.$this->normalized($row['fields']['middle_name']).':'.$this->normalized($row['fields']['suffix']);
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
            if ($row['will_import_voter']) {
                $number = $row['fields']['comelec_voter_number'];
                if (isset($seenVoterNumbers[$number])) {
                    $row['errors'][] = 'This COMELEC voter number appears in another selected row.';
                }
                $seenVoterNumbers[$number] = true;
            }
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

        return ['rows' => $rows, 'households' => $households, 'summary' => ['households' => count($households), 'residents' => count($rows), 'heads' => count(array_filter($rows, fn (array $row): bool => $row['is_head'] && $row['action'] !== 'skip')), 'duplicates' => count(array_filter($rows, fn (array $row): bool => $row['matches'] !== [] || ($row['file_duplicate'] ?? false))), 'invalid' => count(array_filter($rows, fn (array $row): bool => $row['errors'] !== [])), 'existing' => count(array_filter($rows, fn (array $row): bool => $row['already_exists'] && $row['action'] === 'skip')), 'ready' => count(array_filter($rows, fn (array $row): bool => $row['errors'] === [] && $row['action'] !== 'skip')), 'voters' => count(array_filter($rows, fn (array $row): bool => $row['will_import_voter'] && $row['errors'] === []))]];
    }

    /** @param array<string, mixed> $preview
     * @return array{created: int, linked: int, skipped: int, households: int, voters: int}
     */
    public function save(array $preview, Request $request): array
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

            return DB::transaction(function () use ($preview, $request): array {
                DB::table('household_number_sequences')->where('id', 1)->lockForUpdate()->firstOrFail();
                $counts = ['created' => 0, 'linked' => 0, 'skipped' => count(array_filter($preview['rows'], fn (array $row): bool => $row['action'] === 'skip')), 'households' => 0, 'voters' => 0];
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
                            $attributes = Arr::only($fields, ['first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'gender', 'civil_status', 'purok', 'address', 'contact_number', 'residency_type']);
                            $attributes['special_groups'] = $this->specialGroups($fields['special_groups']);
                            $resident = $this->households->saveResident($attributes);
                            $counts['created']++;
                        }
                        if (isset($usedResidents[$resident->id])) {
                            throw ValidationException::withMessages(['rows.'.$id => 'A resident can only be imported once. Skip the repeated row.']);
                        }
                        $usedResidents[$resident->id] = true;
                        $this->households->assign($resident, $household->id, $fields['relationship_to_household_head'] ?: null, $row['is_head']);
                        $counts['voters'] += $this->importVoter($request, $resident, $fields);
                    }
                }

                foreach ($preview['rows'] as $row) {
                    if ($row['action'] !== 'voter_only') {
                        continue;
                    }
                    $resident = $this->identity->matches($row['fields'], null, true)->firstWhere('id', $row['resident_id']);
                    if ($resident === null || $resident->trashed() || $resident->status !== 'active' || isset($usedResidents[$resident->id])) {
                        throw ValidationException::withMessages(['rows.'.$row['id'] => 'The matching active resident changed. Review the voter import again.']);
                    }
                    $usedResidents[$resident->id] = true;
                    $counts['voters'] += $this->importVoter($request, $resident, $row['fields']);
                }

                return $counts;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['file' => 'A resident or voter record was added by another import. Review the file again before saving.']);
        } finally {
            foreach (array_reverse($locks) as $lock) {
                $lock->release();
            }
        }
    }

    /** @param array<string, mixed> $fields */
    private function isSameIdentity(Resident $resident, array $fields): bool
    {
        return $this->normalized($resident->first_name) === $this->normalized($fields['first_name'])
            && $this->normalized($resident->last_name) === $this->normalized($fields['last_name'])
            && $resident->date_of_birth?->toDateString() === $fields['date_of_birth'];
    }

    private function registeredVoter(string $value): ?bool
    {
        return match (Str::lower($value)) {
            'yes', 'true', '1' => true,
            '', 'no', 'false', '0' => false,
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function voterRules(): array
    {
        return array_replace(Arr::only((new StoreVoterRegistrationRequest)->rules(), self::VOTER_FIELDS), [
            'registration_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);
    }

    /** @param array<string, mixed> $fields
     * @param  array<string, mixed>|null  $existing
     * @return list<string>
     */
    private function voterErrors(array $fields, ?array $existing): array
    {
        $registered = $this->registeredVoter($fields['is_registered_voter']);
        if ($registered === null) {
            return ['Registered voter must be Yes or No.'];
        }
        if (! $registered) {
            return array_filter(Arr::only($fields, self::VOTER_FIELDS), filled(...)) === []
                ? [] : ['Select Yes for registered voter when voter details are provided.'];
        }
        if ($existing['registered_voter'] ?? false) {
            return [];
        }

        $errors = Validator::make($fields, $this->voterRules())->errors()->all();
        if ($existing !== null && (! $existing['can_head'] || $existing['archived'])) {
            $errors[] = 'Only active residents can be added to the voters list.';
        }
        if (Gate::denies('voters.import')) {
            $errors[] = 'Voters import permission is required to add voter records.';
        }
        if ($fields['comelec_voter_number'] !== '' && VoterRegistration::query()->where('comelec_voter_number_hash', VoterRegistration::identifierHash($fields['comelec_voter_number']))->exists()) {
            $errors[] = 'This COMELEC voter number is already registered.';
        }
        if ($errors === []) {
            $birthDate = $existing['date_of_birth'] ?? $fields['date_of_birth'];
            if ($birthDate !== '' && Carbon::parse($birthDate)->diffInYears(Carbon::parse($fields['registration_date'])) < 15) {
                $errors[] = 'The resident must be at least 15 years old on the registration date.';
            }
        }

        return array_values($errors);
    }

    /** @param array<string, mixed> $fields */
    private function importVoter(Request $request, Resident $resident, array $fields): int
    {
        if ($this->registeredVoter($fields['is_registered_voter']) !== true) {
            return 0;
        }
        Gate::authorize('voters.import');
        $resident = Resident::query()->whereKey($resident->id)->lockForUpdate()->firstOrFail();
        if ($resident->voterRegistration()->exists()) {
            return 0;
        }
        $validated = Validator::make($fields, $this->voterRules())->validate();
        $this->voters->handle($request, [...$validated, 'resident_id' => $resident->id, 'status' => 'active']);

        return 1;
    }

    /** @return list<string>|null */
    private function specialGroups(string $value): ?array
    {
        if ($value === '') {
            return [];
        }

        try {
            $groups = json_decode($value, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($groups)) {
            return null;
        }

        $normalized = [];
        foreach ($groups as $group) {
            if (! is_string($group)) {
                return null;
            }
            $normalized[] = Str::squish($group);
        }

        return array_values(array_unique($normalized));
    }

    private function normalized(?string $value): string
    {
        return Str::lower(Str::squish((string) $value));
    }
}
