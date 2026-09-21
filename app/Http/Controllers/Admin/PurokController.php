<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePurokRequest;
use App\Models\Purok;
use App\Models\Resident;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PurokController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Purok::class);

        $puroks = Purok::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $activeResidents = Resident::query()
            ->where('status', 'active')
            ->get([
                'resident_number', 'first_name', 'middle_name', 'last_name', 'suffix',
                'date_of_birth', 'gender', 'purok', 'status', 'special_groups',
            ]);
        $ageGroups = [
            'children' => $activeResidents->filter(fn (Resident $resident): bool => $resident->age <= 12)->count(),
            'youth' => $activeResidents->filter(fn (Resident $resident): bool => $resident->age >= 13 && $resident->age <= 17)->count(),
            'young_adults' => $activeResidents->filter(fn (Resident $resident): bool => $resident->age >= 18 && $resident->age <= 35)->count(),
            'middle_age' => $activeResidents->filter(fn (Resident $resident): bool => $resident->age >= 36 && $resident->age <= 59)->count(),
            'seniors' => $activeResidents->filter(fn (Resident $resident): bool => $resident->age >= 60)->count(),
        ];
        $specialGroupCounts = [];
        $purokBreakdown = [];

        foreach ($puroks as $purok) {
            $purokBreakdown[$purok->name] = [
                'residents' => 0,
                'seniors' => 0,
                'pwd' => 0,
                'four_ps' => 0,
            ];
        }

        foreach ($activeResidents as $resident) {
            $specialGroups = $resident->special_groups;

            if (isset($purokBreakdown[$resident->purok])) {
                $purokBreakdown[$resident->purok]['residents']++;
                $purokBreakdown[$resident->purok]['seniors'] += $resident->age >= 60 ? 1 : 0;
            }

            if (! is_array($specialGroups)) {
                continue;
            }

            foreach ($specialGroups as $specialGroup) {
                $specialGroupCounts[$specialGroup] = ($specialGroupCounts[$specialGroup] ?? 0) + 1;

                if (isset($purokBreakdown[$resident->purok]) && $specialGroup === 'PWD') {
                    $purokBreakdown[$resident->purok]['pwd']++;
                }

                if (isset($purokBreakdown[$resident->purok]) && $specialGroup === '4Ps Beneficiary') {
                    $purokBreakdown[$resident->purok]['four_ps']++;
                }
            }
        }

        return response()->json([
            'data' => $puroks->map(fn (Purok $purok): array => [
                'id' => $purok->id,
                'name' => $purok->name,
                'color' => $purok->color,
                'residents_count' => $purokBreakdown[$purok->name]['residents'],
                'senior_count' => $purokBreakdown[$purok->name]['seniors'],
                'pwd_count' => $purokBreakdown[$purok->name]['pwd'],
                'four_ps_count' => $purokBreakdown[$purok->name]['four_ps'],
            ]),
            'demographics' => [
                'total' => $activeResidents->count(),
                'male' => $activeResidents->where('gender', 'Male')->count(),
                'female' => $activeResidents->where('gender', 'Female')->count(),
                'seniors' => $activeResidents->filter(fn (Resident $resident): bool => $resident->age >= 60)->count(),
                'age_groups' => $ageGroups,
                'special_groups' => $specialGroupCounts,
                'senior_residents' => $activeResidents
                    ->filter(fn (Resident $resident): bool => $resident->age >= 60)
                    ->sortBy('last_name')
                    ->take(100)
                    ->map(fn (Resident $resident): array => [
                        'resident_number' => $resident->resident_number,
                        'full_name' => $resident->full_name,
                        'age' => $resident->age,
                        'date_of_birth' => $resident->date_of_birth->toDateString(),
                        'purok' => $resident->purok,
                        'status' => $resident->status,
                    ])
                    ->values(),
            ],
        ]);
    }

    public function store(StorePurokRequest $request): JsonResponse
    {
        $purok = Purok::query()->create($request->validated());

        return response()->json([
            'message' => 'Purok added successfully.',
            'purok' => $purok,
        ], 201);
    }
}
