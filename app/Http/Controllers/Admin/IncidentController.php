<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RecordCaseActivity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreIncidentRequest;
use App\Http\Requests\Admin\UpdateIncidentRequest;
use App\Models\Incident;
use App\Models\Resident;
use App\Models\ResidentRequestRestriction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncidentController extends Controller
{
    public function options(): JsonResponse
    {
        Gate::authorize('viewAny', Incident::class);

        return response()->json(['staff' => User::query()->whereIn('role', ['admin', 'staff'])->where('is_active', true)->whereNull('resident_id')->orderBy('name')->get(['id', 'name'])]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Incident::class);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(Incident::STATUSES)],
            'incident_type' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'resident_id' => ['nullable', 'integer', 'exists:residents,id'],
            'severity' => ['nullable', 'in:low,medium,high'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = Incident::query()
            ->with(['reporter:id,name', 'assignee:id,name', 'complainant', 'respondent'])
            ->when($validated['incident_type'] ?? null, fn (Builder $query, string $type) => $query->where('incident_type', $type))
            ->when($validated['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('occurred_at', '>=', $date))
            ->when($validated['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('occurred_at', '<=', $date))
            ->when($validated['assigned_to'] ?? null, fn (Builder $query, int $id) => $query->where('assigned_to', $id))
            ->when($validated['resident_id'] ?? null, fn (Builder $query, int $id) => $query->where(fn (Builder $query) => $query->where('complainant_resident_id', $id)->orWhere('respondent_resident_id', $id)))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($validated['severity'] ?? null, fn (Builder $query, string $severity) => $query->where('severity', $severity))
            ->when($validated['search'] ?? null, function (Builder $query, string $search): void {
                $term = '%'.trim($search).'%';
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('incident_number', 'like', $term)
                        ->orWhere('incident_type', 'like', $term)
                        ->orWhere('location', 'like', $term)
                        ->orWhere('complainant_name', 'like', $term)
                        ->orWhere('respondent_name', 'like', $term)
                        ->orWhereHas('complainant', fn (Builder $query) => $query->where('last_name', 'like', $term)->orWhere('first_name', 'like', $term)->orWhere('resident_number', 'like', $term))
                        ->orWhereHas('respondent', fn (Builder $query) => $query->where('last_name', 'like', $term)->orWhere('first_name', 'like', $term)->orWhere('resident_number', 'like', $term));
                });
            })
            ->latest('occurred_at')
            ->latest('id');
        $incidents = $query->paginate((int) ($validated['per_page'] ?? 15));

        return response()->json([
            ...$incidents->toArray(),
            'data' => $incidents->getCollection()->map(fn (Incident $incident): array => $this->incidentData($incident)),
            'summary' => [
                'pending' => Incident::query()->whereIn('status', ['open', 'under_review', 'referred', 'pending', 'under_investigation'])->count(),
                'resolved_this_month' => Incident::query()->where('status', 'resolved')->whereBetween('resolved_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
                'high' => Incident::query()->where('severity', 'high')->whereNotIn('status', ['resolved', 'closed', 'dismissed'])->count(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreIncidentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $attachments = $this->storeAttachments($request);

        try {
            $incident = Incident::query()->create([
                ...$this->partyNames(Arr::except($validated, ['occurred_date', 'occurred_time', 'attachments'])),
                'occurred_at' => $this->occurredAt($validated),
                'status' => 'open',
                'attachments' => $attachments,
                'reported_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $this->recordEvent($incident, $request->user(), 'admin.incidents.store', null, array_keys($validated));
        } catch (\Throwable $exception) {
            $this->deleteStoredAttachments($attachments);
            throw $exception;
        }

        return response()->json([
            'message' => 'Incident report filed successfully.',
            'incident' => $this->incidentData($incident->load('reporter', 'assignee')),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Incident $incident): JsonResponse
    {
        Gate::authorize('view', $incident);

        return response()->json([
            ...$this->incidentData($incident->load('reporter', 'assignee')),
            'history' => $incident->events()->with('actor:id,name')->oldest('id')->get()->map(fn ($event) => [
                'action' => $event->action, 'previous_status' => $event->previous_status,
                'status' => $event->status, 'actor' => $event->actor?->name, 'created_at' => $event->created_at,
            ]),
            'restrictions' => ResidentRequestRestriction::query()->where('incident_id', $incident->id)->get(['id', 'resident_id', 'affected_document_type', 'status', 'starts_at', 'ends_at']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateIncidentRequest $request, Incident $incident): JsonResponse
    {
        $validated = $request->validated();
        $incident = Incident::query()->lockForUpdate()->findOrFail($incident->id);
        $previousStatus = $incident->status;
        $newAttachments = $this->storeAttachments($request);
        $attachments = [...($incident->attachments ?? []), ...$newAttachments];

        try {
            $incident->fill([
                ...$this->partyNames([
                    'complainant_resident_id' => $incident->complainant_resident_id,
                    'respondent_resident_id' => $incident->respondent_resident_id,
                    ...Arr::except($validated, ['occurred_date', 'occurred_time', 'attachments']),
                ]),
                'occurred_at' => $this->occurredAt($validated),
                'attachments' => $attachments,
                'updated_by' => $request->user()->id,
                'resolved_at' => $validated['status'] === 'resolved'
                    ? ($incident->resolved_at ?? now())
                    : null,
            ]);
            $changed = array_keys($incident->getDirty());
            $incident->save();
            $this->recordEvent($incident, $request->user(), 'admin.incidents.update', $previousStatus, $changed);
            if ($previousStatus !== $incident->status) {
                app(RecordCaseActivity::class)->handle($request->user(), $incident->status === 'closed' ? 'admin.incidents.closed' : 'admin.incidents.status-changed', $incident->incident_number, ['status']);
            }
        } catch (\Throwable $exception) {
            $this->deleteStoredAttachments($newAttachments);
            throw $exception;
        }

        return response()->json([
            'message' => 'Incident report updated successfully.',
            'incident' => $this->incidentData($incident->fresh()->load('reporter', 'assignee')),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Incident $incident): JsonResponse
    {
        Gate::authorize('delete', $incident);
        $incident->delete();
        app(RecordCaseActivity::class)->handle(request()->user(), 'admin.incidents.destroy', $incident->incident_number, ['deleted_at']);

        return response()->json(['message' => 'Incident report archived successfully.']);
    }

    public function attachment(Incident $incident, int $attachment): StreamedResponse
    {
        Gate::authorize('view', $incident);
        $storedAttachment = ($incident->attachments ?? [])[$attachment] ?? null;

        abort_if($storedAttachment === null, 404);
        abort_unless(Storage::disk('local')->exists($storedAttachment['path']), 404);

        return Storage::disk('local')->download($storedAttachment['path'], $storedAttachment['name']);
    }

    /** @param array<string, mixed> $validated */
    private function occurredAt(array $validated): Carbon
    {
        return Carbon::parse($validated['occurred_date'].' '.(($validated['occurred_time'] ?? null) ?: '00:00'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function partyNames(array $data): array
    {
        foreach (['complainant_resident_id' => 'complainant_name', 'respondent_resident_id' => 'respondent_name'] as $id => $name) {
            if (! empty($data[$id])) {
                $data[$name] = Resident::query()->whereKey($data[$id])->firstOrFail()->full_name;
            }
        }

        return $data;
    }

    /** @param list<string> $changed */
    private function recordEvent(Incident $incident, User $actor, string $action, ?string $previous, array $changed): void
    {
        $incident->events()->create(['user_id' => $actor->id, 'action' => $action, 'previous_status' => $previous, 'status' => $incident->status, 'changed_fields' => $changed]);
        app(RecordCaseActivity::class)->handle($actor, $action, $incident->incident_number, $changed);
    }

    /** @return list<array{name: string, path: string, mime: string, size: int}> */
    private function storeAttachments(Request $request): array
    {
        $attachments = [];

        try {
            foreach ($request->file('attachments', []) as $file) {
                $path = $file->store('incident-attachments', 'local');
                $size = $file->getSize();

                if ($path === false || $size === false) {
                    throw new RuntimeException('The incident attachment could not be stored.');
                }

                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime' => $file->getMimeType() ?? 'application/octet-stream',
                    'size' => $size,
                ];
            }
        } catch (\Throwable $exception) {
            $this->deleteStoredAttachments($attachments);
            throw $exception;
        }

        return $attachments;
    }

    /** @param list<array{name: string, path: string, mime: string, size: int}> $attachments */
    private function deleteStoredAttachments(array $attachments): void
    {
        Storage::disk('local')->delete(array_column($attachments, 'path'));
    }

    /** @return array<string, mixed> */
    private function incidentData(Incident $incident): array
    {
        return [
            'id' => $incident->id,
            'complainant_resident_id' => $incident->complainant_resident_id,
            'respondent_resident_id' => $incident->respondent_resident_id,
            'assigned_to' => $incident->assigned_to,
            'reported_by' => $incident->reported_by,
            'updated_by' => $incident->updated_by,
            'created_at' => $incident->created_at,
            'updated_at' => $incident->updated_at,
            'remarks' => $incident->remarks,
            'incident_number' => $incident->incident_number,
            'incident_type' => $incident->incident_type,
            'occurred_date' => $incident->occurred_at->toDateString(),
            'occurred_time' => $incident->occurred_at->format('H:i'),
            'occurred_at_display' => $incident->occurred_at->format('M j, Y g:i A'),
            'location' => $incident->location,
            'complainant_name' => $incident->complainant_name ?: 'Anonymous',
            'respondent_name' => $incident->respondent_name,
            'severity' => $incident->severity,
            'severity_label' => Str::headline($incident->severity),
            'details' => $incident->details,
            'status' => $incident->status,
            'status_label' => Str::headline($incident->status),
            'resolution_notes' => $incident->resolution_notes,
            'reporter_name' => $incident->reporter?->name,
            'assignee_name' => $incident->assignee?->name,
            'attachments' => collect($incident->attachments ?? [])->map(fn (array $attachment, int $index): array => [
                'name' => $attachment['name'],
                'mime' => $attachment['mime'],
                'size' => $attachment['size'],
                'url' => route('admin.incidents.attachments.show', [$incident, $index]),
            ])->values(),
        ];
    }
}
