<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CabinetDevice;
use App\Models\FileMovementEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RfidFileTrackingController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', Rule::in([FileMovementEvent::REMOVED, FileMovementEvent::RETURNED])],
            'date' => ['nullable', 'date'],
            'cabinet' => ['nullable', 'integer', 'exists:cabinet_devices,id'],
            'drawer' => ['nullable', 'string', 'max:100'],
        ]);

        $movements = FileMovementEvent::query()
            ->with(['user:id,name', 'cabinetDevice:id,identifier,name'])
            ->addSelect(['current_action' => FileMovementEvent::query()
                ->from('file_movement_events as latest')
                ->select('latest.action')
                ->whereColumn('latest.file_reference', 'file_movement_events.file_reference')
                ->orderByDesc('latest.occurred_at')
                ->orderByDesc('latest.id')
                ->limit(1)])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $term = '%'.trim($search).'%';
                    $query->where('file_reference', 'like', $term)
                        ->orWhere('file_name', 'like', $term)
                        ->orWhere('rfid_tag', 'like', $term)
                        ->orWhereHas('user', fn (Builder $users) => $users->where('name', 'like', $term));
                });
            })
            ->when($filters['action'] ?? null, fn (Builder $query, string $action) => $query->where('action', $action))
            ->when($filters['date'] ?? null, fn (Builder $query, string $date) => $query->whereDate('occurred_at', $date))
            ->when($filters['cabinet'] ?? null, fn (Builder $query, int $cabinet) => $query->where('cabinet_device_id', $cabinet))
            ->when($filters['drawer'] ?? null, fn (Builder $query, string $drawer) => $query->where('drawer_reference', 'like', '%'.trim($drawer).'%'))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $cabinets = CabinetDevice::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.iot.rfid-file-tracking', compact('movements', 'filters', 'cabinets'));
    }
}
