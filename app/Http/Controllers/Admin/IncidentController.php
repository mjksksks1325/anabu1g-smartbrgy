<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreIncidentRequest;
use App\Http\Requests\Admin\UpdateIncidentRequest;
use App\Models\Incident;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncidentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Incident::class);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,under_investigation,resolved,dismissed'],
            'severity' => ['nullable', 'in:low,medium,high'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = Incident::query()
            ->with(['reporter:id,name', 'assignee:id,name'])
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($validated['severity'] ?? null, fn (Builder $query, string $severity) => $query->where('severity', $severity))
            ->when($validated['search'] ?? null, function (Builder $query, string $search): void {
                $term = '%'.trim($search).'%';
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('incident_number', 'like', $term)
                        ->orWhere('incident_type', 'like', $term)
                        ->orWhere('location', 'like', $term)
                        ->orWhere('complainant_name', 'like', $term)
                        ->orWhere('respondent_name', 'like', $term);
                });
            })
            ->latest('occurred_at')
            ->latest('id');
        $incidents = $query->paginate((int) ($validated['per_page'] ?? 15));

        return response()->json([
            ...$incidents->toArray(),
            'data' => $incidents->getCollection()->map(fn (Incident $incident): array => $this->incidentData($incident)),
            'summary' => [
                'pending' => Incident::query()->whereIn('status', ['pending', 'under_investigation'])->count(),
                'resolved_this_month' => Incident::query()->where('status', 'resolved')->whereBetween('resolved_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
                'high' => Incident::query()->where('severity', 'high')->whereNotIn('status', ['resolved', 'dismissed'])->count(),
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
                ...Arr::except($validated, ['occurred_date', 'occurred_time', 'attachments']),
                'occurred_at' => $this->occurredAt($validated),
                'status' => 'pending',
                'attachments' => $attachments,
                'reported_by' => $request->user()->id,
            ]);
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

        return response()->json($this->incidentData($incident->load('reporter', 'assignee')));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateIncidentRequest $request, Incident $incident): JsonResponse
    {
        $validated = $request->validated();
        $newAttachments = $this->storeAttachments($request);
        $attachments = [...($incident->attachments ?? []), ...$newAttachments];

        try {
            $incident->update([
                ...Arr::except($validated, ['occurred_date', 'occurred_time', 'attachments']),
                'occurred_at' => $this->occurredAt($validated),
                'attachments' => $attachments,
                'resolved_at' => $validated['status'] === 'resolved'
                    ? ($incident->resolved_at ?? now())
                    : null,
            ]);
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
        return Carbon::parse($validated['occurred_date'].' '.($validated['occurred_time'] ?: '00:00'));
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
