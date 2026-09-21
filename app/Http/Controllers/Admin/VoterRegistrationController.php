<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVoterRegistrationRequest;
use App\Http\Requests\Admin\UpdateVoterRegistrationRequest;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\VoterRegistration;
use App\Models\VoterRegistrationAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VoterRegistrationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', VoterRegistration::class);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'purok' => ['nullable', 'string', 'max:100', Rule::exists('puroks', 'name')->where('is_active', true)],
            'eligibility' => ['nullable', Rule::in(['sk_only', 'sk_and_regular', 'regular_only'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $registrations = $this->registrationQuery($validated)
            ->paginate((int) ($validated['per_page'] ?? 15));

        $activeVoters = VoterRegistration::query()->where('status', 'active');
        $skOnly = (clone $activeVoters)->whereHas(
            'resident',
            fn (Builder $query): Builder => $this->applyEligibilityFilter(
                $query->where('status', 'active')->whereNull('deleted_at'),
                'sk_only',
            ),
        )->count();
        $skAndRegular = (clone $activeVoters)->whereHas(
            'resident',
            fn (Builder $query): Builder => $this->applyEligibilityFilter(
                $query->where('status', 'active')->whereNull('deleted_at'),
                'sk_and_regular',
            ),
        )->count();
        $regularOnly = (clone $activeVoters)->whereHas(
            'resident',
            fn (Builder $query): Builder => $this->applyEligibilityFilter(
                $query->where('status', 'active')->whereNull('deleted_at'),
                'regular_only',
            ),
        )->count();
        $eligibleResidents = Resident::query()
            ->where('status', 'active')
            ->whereDate('date_of_birth', '<=', today()->subYears(15))
            ->whereDoesntHave('voterRegistration')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(500)
            ->get(['id', 'resident_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'purok']);

        return response()->json([
            ...$registrations->toArray(),
            'data' => $registrations->getCollection()->map(fn (VoterRegistration $registration): array => $this->registrationData($registration)),
            'summary' => [
                'total' => $skOnly + $skAndRegular + $regularOnly,
                'sk_only' => $skOnly,
                'sk_and_regular' => $skAndRegular,
                'regular_only' => $regularOnly,
            ],
            'puroks' => Purok::query()->where('is_active', true)->orderBy('name')->pluck('name'),
            'eligible_residents' => $eligibleResidents->map(function (Resident $resident): array {
                $eligibility = $this->voterEligibility($resident->age);

                return [
                    'id' => $resident->id,
                    'resident_number' => $resident->resident_number,
                    'full_name' => $resident->full_name,
                    'age' => $resident->age,
                    'purok' => $resident->purok,
                    'voter_eligibility_label' => $eligibility['label'],
                ];
            }),
        ]);
    }

    public function store(StoreVoterRegistrationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $identifier = $validated['comelec_voter_number'];
        $identifierHash = VoterRegistration::identifierHash($identifier);

        if (VoterRegistration::query()->where('comelec_voter_number_hash', $identifierHash)->exists()) {
            throw ValidationException::withMessages([
                'comelec_voter_number' => 'This COMELEC voter number is already registered.',
            ]);
        }

        $registration = DB::transaction(function () use ($request, $validated, $identifier, $identifierHash): VoterRegistration {
            $resident = Resident::query()
                ->whereKey($validated['resident_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $registrationDate = Carbon::parse($validated['registration_date']);

            if ($resident->date_of_birth->diffInYears($registrationDate) < 15) {
                throw ValidationException::withMessages([
                    'resident_id' => 'The resident must be at least 15 years old on the registration date.',
                ]);
            }

            if ($resident->status !== 'active') {
                throw ValidationException::withMessages([
                    'resident_id' => 'Only active residents can be added to the voter registry.',
                ]);
            }

            $registration = VoterRegistration::query()->create([
                ...$validated,
                'comelec_voter_number' => $identifier,
                'comelec_voter_number_hash' => $identifierHash,
                'created_by' => $request->user()->id,
            ]);

            $this->recordAudit($request, $registration, 'created', [
                'resident_id' => $resident->id,
                'voter_number' => $registration->voter_number,
                'masked_comelec_voter_number' => $registration->masked_comelec_voter_number,
                'status' => $registration->status,
            ]);

            return $registration;
        });

        return response()->json([
            'message' => 'Voter registration saved securely.',
            'registration' => $this->registrationData($registration->load('resident')),
        ], 201);
    }

    public function update(
        UpdateVoterRegistrationRequest $request,
        VoterRegistration $voterRegistration,
    ): JsonResponse {
        $validated = $request->validated();

        $registration = DB::transaction(function () use ($request, $validated, $voterRegistration): VoterRegistration {
            $locked = VoterRegistration::query()->lockForUpdate()->findOrFail($voterRegistration->id);

            if (! $locked->integrity_valid) {
                abort(409, 'Record integrity check failed. Updates are blocked pending administrator review.');
            }

            if ($locked->version !== (int) $validated['version']) {
                throw ValidationException::withMessages([
                    'version' => 'This record was changed by another user. Reload it before saving.',
                ]);
            }

            $before = [
                'precinct_number' => $locked->precinct_number,
                'cluster_number' => $locked->cluster_number,
                'registration_date' => $locked->registration_date->toDateString(),
                'status' => $locked->status,
                'version' => $locked->version,
            ];

            $attributes = Arr::except($validated, ['version', 'comelec_voter_number']);
            $identifier = $validated['comelec_voter_number'] ?? null;

            if (is_string($identifier) && $identifier !== '') {
                $identifierHash = VoterRegistration::identifierHash($identifier);
                $duplicateExists = VoterRegistration::query()
                    ->where('comelec_voter_number_hash', $identifierHash)
                    ->whereKeyNot($locked->id)
                    ->exists();

                if ($duplicateExists) {
                    throw ValidationException::withMessages([
                        'comelec_voter_number' => 'This COMELEC voter number is already registered.',
                    ]);
                }

                $attributes['comelec_voter_number'] = $identifier;
                $attributes['comelec_voter_number_hash'] = $identifierHash;
            }

            $locked->update([...$attributes, 'updated_by' => $request->user()->id]);

            $this->recordAudit($request, $locked, 'updated', [
                'before' => $before,
                'after' => [
                    'precinct_number' => $locked->precinct_number,
                    'cluster_number' => $locked->cluster_number,
                    'registration_date' => $locked->registration_date->toDateString(),
                    'status' => $locked->status,
                    'version' => $locked->version,
                ],
                'comelec_number_changed' => is_string($identifier) && $identifier !== '',
            ]);

            return $locked;
        });

        return response()->json([
            'message' => 'Voter registration updated securely.',
            'registration' => $this->registrationData($registration->load('resident')),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', VoterRegistration::class);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'purok' => ['nullable', 'string', 'max:100', Rule::exists('puroks', 'name')->where('is_active', true)],
            'eligibility' => ['nullable', Rule::in(['sk_only', 'sk_and_regular', 'regular_only'])],
        ]);
        $registrations = $this->registrationQuery($validated);

        return response()->streamDownload(function () use ($registrations): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fputcsv($output, ['Resident ID', 'Full Name', 'Age', 'Purok', 'Voter Eligibility', 'Precinct No.', 'Cluster No.']);

            foreach ($registrations->cursor() as $registration) {
                fputcsv($output, array_map($this->csvValue(...), [
                    $registration->resident->resident_number,
                    $registration->resident->full_name,
                    $registration->resident->age,
                    $registration->resident->purok,
                    $this->voterEligibility($registration->resident->age)['label'],
                    $registration->precinct_number,
                    $registration->cluster_number,
                ]));
            }

            fclose($output);
        }, 'voter-registry-'.today()->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @param array<string, mixed> $filters
     * @return Builder<VoterRegistration>
     */
    private function registrationQuery(array $filters): Builder
    {
        return VoterRegistration::query()
            ->with('resident:id,resident_number,first_name,middle_name,last_name,suffix,purok,status,date_of_birth')
            ->where('status', 'active')
            ->whereHas('resident', function (Builder $query) use ($filters): void {
                $query->where('status', 'active')
                    ->whereNull('deleted_at')
                    ->whereDate('date_of_birth', '<=', today()->subYears(15));

                if (isset($filters['purok'])) {
                    $query->where('purok', $filters['purok']);
                }

                if (isset($filters['eligibility'])) {
                    $this->applyEligibilityFilter($query, $filters['eligibility']);
                }
            })
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $term = '%'.trim($search).'%';
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('voter_number', 'like', $term)
                        ->orWhere('precinct_number', 'like', $term)
                        ->orWhere('cluster_number', 'like', $term)
                        ->orWhereHas('resident', function (Builder $query) use ($term): void {
                            $query->where('resident_number', 'like', $term)
                                ->orWhere('first_name', 'like', $term)
                                ->orWhere('middle_name', 'like', $term)
                                ->orWhere('last_name', 'like', $term)
                                ->orWhere('purok', 'like', $term);
                        });
                });
            })
            ->latest('registration_date')
            ->latest('id');
    }

    /** @param Builder<Resident> $query
     * @return Builder<Resident>
     */
    private function applyEligibilityFilter(Builder $query, string $eligibility): Builder
    {
        return match ($eligibility) {
            'sk_only' => $query
                ->whereDate('date_of_birth', '<=', today()->subYears(15))
                ->whereDate('date_of_birth', '>', today()->subYears(18)),
            'sk_and_regular' => $query
                ->whereDate('date_of_birth', '<=', today()->subYears(18))
                ->whereDate('date_of_birth', '>', today()->subYears(31)),
            'regular_only' => $query->whereDate('date_of_birth', '<=', today()->subYears(31)),
            default => throw new \InvalidArgumentException('Invalid voter eligibility filter.'),
        };
    }

    /** @return array<string, mixed> */
    private function registrationData(VoterRegistration $registration): array
    {
        $eligibility = $this->voterEligibility($registration->resident->age);

        return [
            'id' => $registration->id,
            'voter_number' => $registration->voter_number,
            'resident_id' => $registration->resident_id,
            'resident_number' => $registration->resident->resident_number,
            'resident_name' => $registration->resident->full_name,
            'age' => $registration->resident->age,
            'purok' => $registration->resident->purok,
            'masked_comelec_voter_number' => $registration->masked_comelec_voter_number,
            'precinct_number' => $registration->precinct_number,
            'cluster_number' => $registration->cluster_number,
            'registration_date' => $registration->registration_date->toDateString(),
            'status' => $registration->status,
            'version' => $registration->version,
            'integrity_valid' => $registration->integrity_valid,
            'voter_eligibility' => $eligibility['key'],
            'voter_eligibility_label' => $eligibility['label'],
        ];
    }

    /** @return array{key: string, label: string} */
    private function voterEligibility(int $age): array
    {
        if ($age < 18) {
            return ['key' => 'sk_only', 'label' => 'SK Voter Lamang'];
        }

        if ($age <= 30) {
            return ['key' => 'sk_and_regular', 'label' => 'SK at Regular Voter'];
        }

        return ['key' => 'regular_only', 'label' => 'Regular Voter Lamang'];
    }

    /** @param array<string, mixed> $changes */
    private function recordAudit(
        Request $request,
        VoterRegistration $registration,
        string $action,
        array $changes,
    ): void {
        $key = hash('sha256', (string) config('app.key'));

        VoterRegistrationAudit::query()->create([
            'voter_registration_id' => $registration->id,
            'actor_id' => $request->user()?->id,
            'action' => $action,
            'changes' => $changes,
            'ip_hash' => $request->ip() ? hash_hmac('sha256', $request->ip(), $key) : null,
            'user_agent_hash' => $request->userAgent() ? hash_hmac('sha256', $request->userAgent(), $key) : null,
        ]);
    }

    private function csvValue(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'{$value}" : $value;
    }
}
