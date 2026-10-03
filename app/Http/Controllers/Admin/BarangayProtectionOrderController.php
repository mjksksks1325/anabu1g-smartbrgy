<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RecordCaseActivity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveBarangayProtectionOrderRequest;
use App\Models\BarangayProtectionOrder;
use App\Models\Resident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BarangayProtectionOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', BarangayProtectionOrder::class);
        $validated = $request->validate(['incident_id' => ['nullable', 'integer', 'exists:incidents,id'], 'page' => ['nullable', 'integer', 'min:1']]);

        return response()->json(BarangayProtectionOrder::query()->when($validated['incident_id'] ?? null, fn ($query, $id) => $query->where('incident_id', $id))->latest()->paginate(15));
    }

    public function show(BarangayProtectionOrder $protectionOrder): JsonResponse
    {
        Gate::authorize('view', $protectionOrder);

        return response()->json($protectionOrder);
    }

    public function store(SaveBarangayProtectionOrderRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request): JsonResponse {
            $order = BarangayProtectionOrder::query()->create([...$this->partyNames($request->validated()), 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
            app(RecordCaseActivity::class)->handle($request->user(), 'admin.protection-orders.store', (string) $order->id, array_keys($request->validated()));

            return response()->json(['message' => 'Restricted BPO record saved.', 'order' => $order], 201);
        });
    }

    public function update(SaveBarangayProtectionOrderRequest $request, BarangayProtectionOrder $protectionOrder): JsonResponse
    {
        return DB::transaction(function () use ($request, $protectionOrder): JsonResponse {
            $order = BarangayProtectionOrder::query()->lockForUpdate()->findOrFail($protectionOrder->id);
            $previous = $order->status;
            $order->fill([...$this->partyNames([
                'protected_resident_id' => $order->protected_resident_id,
                'respondent_resident_id' => $order->respondent_resident_id,
                ...$request->validated(),
            ]), 'updated_by' => $request->user()->id]);
            $changed = array_keys($order->getDirty());
            $order->save();
            app(RecordCaseActivity::class)->handle($request->user(), 'admin.protection-orders.update', (string) $order->id, $changed);
            if ($previous !== $order->status) {
                app(RecordCaseActivity::class)->handle($request->user(), 'admin.protection-orders.status-changed', (string) $order->id, ['status']);
            }

            return response()->json(['message' => 'Restricted BPO record updated.', 'order' => $order]);
        });
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function partyNames(array $data): array
    {
        foreach (['protected_resident_id' => 'protected_person', 'respondent_resident_id' => 'respondent'] as $id => $name) {
            if (! empty($data[$id])) {
                $data[$name] = Resident::query()->whereKey($data[$id])->firstOrFail()->full_name;
            }
        }

        return $data;
    }
}
