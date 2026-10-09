<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Resident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffSubmissionController extends Controller
{
    public function pending(): View
    {
        return view('staff.access-pending');
    }

    public function create(Request $request): View|RedirectResponse
    {
        if ($request->routeIs('staff.incidents.create') && ! $request->user()->hasAnyPermission(['incidents.view', 'vawc.view'])) {
            return redirect()->route('staff.incidents.index');
        }
        $request->session()->forget('staff_incident_confirmation');

        return view('staff.incident-submission');
    }

    public function confirmation(Request $request): View|RedirectResponse
    {
        $confirmation = $request->session()->get('staff_incident_confirmation');
        if (! is_array($confirmation) || ($confirmation['actor_id'] ?? null) !== $request->user()->id || ! is_string($confirmation['reference'] ?? null)) {
            return redirect()->route('staff.incidents.index');
        }

        return view('staff.incident-submission', ['reference' => $confirmation['reference']]);
    }

    public function residents(Request $request): JsonResponse
    {
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $residents = Resident::query()->select(['id', 'resident_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'gender', 'purok', 'address', 'status', 'household_id'])
            ->when($request->user()->hasPermission('households.view'), fn ($query) => $query->with('household:id,household_number,household_name'))
            ->when($validated['search'] ?? null, function ($query, $search): void {
                $query->where(function ($query) use ($search): void {
                    foreach (['resident_number', 'first_name', 'middle_name', 'last_name'] as $column) {
                        $query->orWhere($column, 'like', '%'.trim($search).'%');
                    }
                });
            })->orderBy('last_name')->paginate((int) ($validated['per_page'] ?? 15));

        return response()->json($residents);
    }
}
