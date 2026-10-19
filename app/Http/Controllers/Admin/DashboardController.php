<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Models\Incident;
use App\Models\IssuedCertificate;
use App\Models\Resident;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): JsonResponse
    {
        Gate::authorize('dashboard.view');
        $user = request()->user();
        $documents = $user->hasPermission('documents.view');
        $residents = $user->hasAnyPermission(['records.view', 'demographics.view']);
        $incidents = $user->hasAnyPermission(['incidents.view', 'vawc.view']);

        $recentRequests = $documents ? $this->recentDocumentRequestActivity() : [];
        $recentIncidents = $incidents ? Incident::query()->visibleTo($user)
            ->latest()
            ->limit(6)
            ->get(['incident_number', 'incident_type', 'status', 'created_at'])
            ->map(fn (Incident $incident): array => [
                'type' => 'incident',
                'title' => 'Incident '.str_replace('_', ' ', $incident->status),
                'detail' => $incident->incident_number.' — '.$incident->incident_type,
                'occurred_at' => $incident->created_at,
            ]) : collect();

        return response()->json([
            'summary' => [
                'active_residents' => $residents ? Resident::query()->where('status', 'active')->count() : null,
                'issued_certificates' => $documents ? IssuedCertificate::query()->count() : null,
                'pending_requests' => $documents ? DocumentRequest::query()->whereIn('status', ['pending', 'processing', 'ready_for_release'])->count() : null,
                'open_incidents' => $incidents ? Incident::query()->visibleTo($user)->whereIn('status', ['open', 'under_review', 'referred', 'pending', 'under_investigation'])->count() : null,
            ],
            'certificate_requests' => $documents ? DocumentRequest::query()
                ->select('document_type')
                ->selectRaw('COUNT(*) as aggregate')
                ->groupBy('document_type')
                ->orderByDesc('aggregate')
                ->pluck('aggregate', 'document_type') : [],
            'recent_activity' => Collection::make([...$recentRequests, ...$recentIncidents])
                ->sortByDesc('occurred_at')
                ->take(6)
                ->values()
                ->map(fn (array $activity): array => [
                    ...$activity,
                    'occurred_at' => $activity['occurred_at']?->toIso8601String(),
                    'time' => $activity['occurred_at']?->diffForHumans(),
                ]),
        ]);
    }

    /**
     * Return recent request changes recorded by staff, with new requests as a fallback.
     *
     * @return array<int, array{type: string, title: string, detail: string, occurred_at: Carbon|CarbonImmutable|null}>
     */
    private function recentDocumentRequestActivity(): array
    {
        $auditEntries = DB::table('administrative_audits')
            ->whereIn('action', [
                'admin.document-requests.update-status',
                'admin.document-requests.issue',
            ])
            ->latest('created_at')
            ->limit(12)
            ->get(['actor', 'action', 'record', 'created_at']);

        $requestIds = $auditEntries
            ->pluck('record')
            ->filter(fn (?string $record): bool => $record !== null && ctype_digit($record))
            ->unique()
            ->values();

        $requestsById = DocumentRequest::query()
            ->whereIn('id', $requestIds)
            ->get(['id', 'reference_code', 'document_type', 'status'])
            ->keyBy(fn (DocumentRequest $documentRequest): string => (string) $documentRequest->id);

        $staffActivity = $auditEntries
            ->filter(fn (object $entry): bool => $requestsById->has((string) $entry->record))
            ->map(function (object $entry) use ($requestsById): array {
                /** @var DocumentRequest $documentRequest */
                $documentRequest = $requestsById->get((string) $entry->record);
                $action = $entry->action === 'admin.document-requests.issue'
                    ? 'released'
                    : 'updated';

                return [
                    'type' => 'request',
                    'title' => 'Document request '.$action.' by '.$entry->actor,
                    'detail' => $documentRequest->reference_code.' — '.$documentRequest->document_type,
                    'occurred_at' => Carbon::parse($entry->created_at),
                ];
            });

        $auditedRequestIds = $auditEntries
            ->pluck('record')
            ->filter(fn (?string $record): bool => $record !== null && ctype_digit($record))
            ->unique();

        $newRequests = DocumentRequest::query()
            ->whereNotIn('id', $auditedRequestIds)
            ->latest()
            ->limit(6)
            ->get(['reference_code', 'document_type', 'status', 'created_at'])
            ->map(fn (DocumentRequest $documentRequest): array => [
                'type' => 'request',
                'title' => 'Document request '.str_replace('_', ' ', $documentRequest->status),
                'detail' => $documentRequest->reference_code.' — '.$documentRequest->document_type,
                'occurred_at' => $documentRequest->created_at,
            ]);

        return $staffActivity->concat($newRequests)->take(6)->values()->all();
    }
}
