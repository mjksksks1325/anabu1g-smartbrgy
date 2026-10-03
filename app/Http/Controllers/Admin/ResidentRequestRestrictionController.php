<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CheckRequestRestrictions;
use App\Actions\RecordCaseActivity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreResidentRequestRestrictionRequest;
use App\Models\Incident;
use App\Models\Resident;
use App\Models\ResidentRequestRestriction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ResidentRequestRestrictionController extends Controller
{
    public function index(Request $request, CheckRequestRestrictions $checks): JsonResponse
    {
        Gate::authorize('viewAny', ResidentRequestRestriction::class);
        $data = $request->validate(['resident_id' => ['nullable', 'integer', 'exists:residents,id'], 'search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:pending_review,active,lifted,expired']]);
        $checks->expire($data['resident_id'] ?? null);

        return response()->json(ResidentRequestRestriction::query()->with('resident:id,first_name,middle_name,last_name,suffix,resident_number')
            ->when($data['resident_id'] ?? null, fn ($q, $id) => $q->where('resident_id', $id))
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($data['search'] ?? null, fn ($q, $term) => $q->whereHas('resident', fn ($r) => $r->where(fn ($r) => $r->where('last_name', 'like', '%'.$term.'%')->orWhere('first_name', 'like', '%'.$term.'%')->orWhere('resident_number', 'like', '%'.$term.'%'))))
            ->latest()->paginate(15));
    }

    public function store(StoreResidentRequestRestrictionRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (! empty($data['incident_id']) && ! Incident::query()->whereKey($data['incident_id'])->where(fn ($q) => $q->where('complainant_resident_id', $data['resident_id'])->orWhere('respondent_resident_id', $data['resident_id']))->exists()) {
            throw ValidationException::withMessages(['incident_id' => 'Select a case linked to this resident.']);
        }

        return DB::transaction(function () use ($request, $data): JsonResponse {
            Resident::query()->whereKey($data['resident_id'])->lockForUpdate()->firstOrFail();
            $restriction = ResidentRequestRestriction::query()->create([...$data, 'status' => 'pending_review', 'created_by' => $request->user()->id]);
            app(RecordCaseActivity::class)->handle($request->user(), 'admin.request-restrictions.store', (string) $restriction->id, array_keys($data));

            return response()->json(['message' => 'Restriction saved as Pending Review. Review and activate it explicitly.', 'restriction' => $restriction], 201);
        });
    }

    public function review(Request $request, ResidentRequestRestriction $restriction): JsonResponse
    {
        Gate::authorize('update', $restriction);

        return DB::transaction(function () use ($request, $restriction): JsonResponse {
            Resident::withTrashed()->whereKey($restriction->resident_id)->lockForUpdate()->firstOrFail();
            $locked = ResidentRequestRestriction::query()->lockForUpdate()->findOrFail($restriction->id);
            if ($locked->status !== 'pending_review' || ($locked->ends_at !== null && $locked->ends_at->lte(now()))) {
                throw ValidationException::withMessages(['restriction' => 'Only a pending, unexpired restriction can be reviewed and activated.']);
            }
            $locked->update(['status' => 'active', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            app(RecordCaseActivity::class)->handle($request->user(), 'admin.request-restrictions.reviewed', (string) $locked->id, ['status', 'reviewed_by', 'reviewed_at']);

            return response()->json(['message' => 'Reviewed restriction activated.', 'restriction' => $locked]);
        });
    }

    public function lift(Request $request, ResidentRequestRestriction $restriction): JsonResponse
    {
        Gate::authorize('update', $restriction);
        $data = $request->validate(['lift_reason' => ['required', 'string', 'max:5000']]);

        return DB::transaction(function () use ($request, $restriction, $data): JsonResponse {
            Resident::withTrashed()->whereKey($restriction->resident_id)->lockForUpdate()->firstOrFail();
            $locked = ResidentRequestRestriction::query()->lockForUpdate()->findOrFail($restriction->id);
            if (! in_array($locked->status, ['active', 'pending_review'], true)) {
                throw ValidationException::withMessages(['restriction' => 'This restriction has already ended.']);
            }
            $locked->update(['status' => 'lifted', 'lifted_by' => $request->user()->id, 'lifted_at' => now(), 'lift_reason' => $data['lift_reason']]);
            app(RecordCaseActivity::class)->handle($request->user(), 'admin.request-restrictions.lifted', (string) $locked->id, ['status', 'lifted_by', 'lifted_at', 'lift_reason']);

            return response()->json(['message' => 'Restriction lifted. History retained.', 'restriction' => $locked]);
        });
    }
}
