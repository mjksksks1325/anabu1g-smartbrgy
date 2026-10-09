<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ManageHouseholds;
use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class HouseholdController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Resident::class);
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100'], 'purok_id' => ['nullable', 'integer', Rule::exists('puroks', 'id')]]);
        $households = Household::query()->with(['head', 'purok'])->withCount('members')
            ->when($validated['purok_id'] ?? null, fn (Builder $query, int $id) => $query->where('purok_id', $id))
            ->when($validated['search'] ?? null, function (Builder $query, string $search): void {
                $term = '%'.trim($search).'%';
                $query->where(function (Builder $query) use ($term, $search): void {
                    $query->where('household_number', 'like', $term)->orWhere('household_name', 'like', $term)->orWhere('address', 'like', $term)
                        ->orWhereHas('head', function (Builder $query) use ($search): void {
                            foreach (explode(' ', trim($search)) as $part) {
                                $query->where(function (Builder $query) use ($part): void {
                                    $term = '%'.$part.'%';
                                    $query->where('first_name', 'like', $term)->orWhere('middle_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhere('suffix', 'like', $term);
                                });
                            }
                        });
                });
            })->orderBy('id')->paginate((int) ($validated['per_page'] ?? 15));
        $households->through(fn (Household $household): array => $household->summary());

        return response()->json($households);
    }

    public function store(Request $request, ManageHouseholds $households): JsonResponse
    {
        Gate::authorize('households.create');
        if ($request->filled('members')) {
            Gate::authorize('households.update');
        }
        $validated = $request->validate($this->addressRules() + [
            'household_number' => ['nullable', 'string', 'max:100', Rule::unique('households', 'household_number')],
            'household_head_resident_id' => ['required_with:members', 'nullable', 'integer', Rule::exists('residents', 'id')->whereNull('deleted_at')->where('status', 'active')],
            'members' => ['sometimes', 'array', 'max:100'],
            'members.*' => ['array:resident_id,relationship_to_household_head'],
            'members.*.resident_id' => ['required', 'integer', 'distinct', Rule::exists('residents', 'id')->whereNull('deleted_at')],
            'members.*.relationship_to_household_head' => ['required', 'string', 'max:100'],
        ]);

        return response()->json(['message' => 'Household created successfully.', 'household' => $households->createWithMembers($validated)->details()], 201);
    }

    public function show(Household $household): JsonResponse
    {
        Gate::authorize('viewAny', Resident::class);

        return response()->json($household->details());
    }

    public function update(Request $request, Household $household, ManageHouseholds $households): JsonResponse
    {
        Gate::authorize('households.update');
        $validated = $request->validate($this->addressRules() + [
            'household_number' => ['prohibited'],
            'household_head_resident_id' => ['sometimes', 'nullable', 'integer', Rule::exists('residents', 'id')->whereNull('deleted_at')->where('status', 'active')],
        ]);

        return response()->json(['message' => 'Household updated successfully.', 'household' => $households->update($household, $validated)->details()]);
    }

    public function member(Request $request, Household $household, Resident $resident, ManageHouseholds $households): JsonResponse
    {
        Gate::authorize('households.update');
        $validated = $request->validate([
            'relationship_to_household_head' => ['nullable', 'string', 'max:100'],
            'is_household_head' => ['sometimes', 'boolean'],
        ]);
        $households->assign($resident, $household->id, $validated['relationship_to_household_head'] ?? null, (bool) ($validated['is_household_head'] ?? false));

        return response()->json(['message' => 'Household member saved successfully.', 'household' => $household->refresh()->details()]);
    }

    public function removeMember(Household $household, Resident $resident, ManageHouseholds $households): JsonResponse
    {
        Gate::authorize('households.remove');
        $households->remove($household, $resident);

        return response()->json(['message' => 'Resident removed from household.', 'household' => $household->refresh()->details()]);
    }

    /** @return array<string, array<mixed>> */
    private function addressRules(): array
    {
        return [
            'household_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:1000'],
            'purok_id' => ['nullable', 'integer', Rule::exists('puroks', 'id')->where('is_active', true)],
        ];
    }
}
