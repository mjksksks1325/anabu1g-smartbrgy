<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Models\Incident;
use App\Models\IssuedCertificate;
use App\Models\Resident;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): JsonResponse
    {
        Gate::authorize('viewAny', Resident::class);
        Gate::authorize('viewAny', Incident::class);

        $recentRequests = DocumentRequest::query()
            ->latest()
            ->limit(6)
            ->get(['reference_code', 'document_type', 'status', 'created_at'])
            ->map(fn (DocumentRequest $documentRequest): array => [
                'type' => 'request',
                'title' => 'Document request '.str_replace('_', ' ', $documentRequest->status),
                'detail' => $documentRequest->reference_code.' — '.$documentRequest->document_type,
                'occurred_at' => $documentRequest->created_at,
            ]);
        $recentIncidents = Incident::query()
            ->latest()
            ->limit(6)
            ->get(['incident_number', 'incident_type', 'status', 'created_at'])
            ->map(fn (Incident $incident): array => [
                'type' => 'incident',
                'title' => 'Incident '.str_replace('_', ' ', $incident->status),
                'detail' => $incident->incident_number.' — '.$incident->incident_type,
                'occurred_at' => $incident->created_at,
            ]);

        return response()->json([
            'summary' => [
                'active_residents' => Resident::query()->where('status', 'active')->count(),
                'issued_certificates' => IssuedCertificate::query()->count(),
                'pending_requests' => DocumentRequest::query()->whereIn('status', ['pending', 'processing', 'ready_for_release'])->count(),
                'open_incidents' => Incident::query()->whereIn('status', ['pending', 'under_investigation'])->count(),
            ],
            'certificate_requests' => DocumentRequest::query()
                ->select('document_type')
                ->selectRaw('COUNT(*) as aggregate')
                ->groupBy('document_type')
                ->orderByDesc('aggregate')
                ->pluck('aggregate', 'document_type'),
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
}
