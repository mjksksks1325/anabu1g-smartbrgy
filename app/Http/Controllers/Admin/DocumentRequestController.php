<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateDocumentRequestStatusRequest;
use App\Models\DocumentRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentRequestController extends Controller
{
    /** Board lanes, in the order staff work through them. Rejected requests leave the board. */
    private const BOARD_LANES = ['online', 'processing', 'ready', 'completed'];

    public function attachment(DocumentRequest $documentRequest): StreamedResponse
    {
        Gate::authorize('view', $documentRequest);
        abort_unless($documentRequest->private_attachment_path && Storage::disk('local')->exists($documentRequest->private_attachment_path), 404);

        return Storage::disk('local')->response($documentRequest->private_attachment_path, null, ['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    /**
     * The paginated request list. Browsers get the full list page; the certificates board asks for JSON one lane at a time,
     * so the workspace never has to embed or poll every request ever made.
     */
    public function index(Request $request): View|JsonResponse
    {
        Gate::authorize('viewAny', DocumentRequest::class);

        if (! $request->expectsJson()) {
            // The full list page filters with the same lane and search rules as the certificates board.
            $lane = in_array($request->query('lane'), self::BOARD_LANES, true) ? $request->query('lane') : null;
            $search = trim($request->string('search')->limit(100, '')->toString());
            $requests = DocumentRequest::query()
                ->when($lane, fn (Builder $query, string $lane) => $this->whereLane($query, $lane))
                ->tap(fn (Builder $query) => $this->whereSearch($query, $search))
                ->latest()
                ->latest('id')
                ->paginate(25)
                ->withQueryString();

            return view('admin.document-requests.index', [
                'requests' => $requests,
                'lane' => $lane,
                'search' => $search,
                'laneCounts' => $this->laneCounts(''),
                'totalRequests' => DocumentRequest::query()->count(),
            ]);
        }

        $filters = $request->validate([
            'lane' => ['nullable', Rule::in(self::BOARD_LANES)],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'with_counts' => ['nullable', 'boolean'],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));

        // Each card carries its issued certificate's number, so request details can lead staff to the reprint.
        $requests = DocumentRequest::query()
            ->with('issuedCertificate:id,document_request_id,certificate_number,revoked_at')
            ->when($filters['lane'] ?? null, fn (Builder $query, string $lane) => $this->whereLane($query, $lane))
            ->tap(fn (Builder $query) => $this->whereSearch($query, $search))
            ->latest()
            ->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();

        $payload = $requests->toArray();

        if ($request->boolean('with_counts')) {
            $payload['counts'] = $this->laneCounts('');
            $payload['matching'] = $this->laneCounts($search);
        }

        return response()->json($payload);
    }

    public function show(DocumentRequest $documentRequest): View
    {
        Gate::authorize('view', $documentRequest);

        return view('admin.document-requests.show', compact('documentRequest'));
    }

    public function updateStatus(
        UpdateDocumentRequestStatusRequest $request,
        DocumentRequest $documentRequest,
    ): JsonResponse|RedirectResponse {
        $validated = $request->validated();
        $lockedRequest = DB::transaction(function () use ($request, $documentRequest, $validated): ?DocumentRequest {
            $locked = DocumentRequest::query()->lockForUpdate()->findOrFail($documentRequest->id);

            if ($locked->issuedCertificate()->exists()) {
                return null;
            }

            $isRejected = $validated['status'] === 'rejected';
            $locked->update([
                'status' => $validated['status'],
                'remarks' => $validated['remarks'] ?? $locked->remarks,
                'rejection_reason' => $isRejected ? $validated['rejection_reason'] : null,
                'rejected_at' => $isRejected ? now() : null,
                'rejected_by' => $isRejected ? $request->user()->id : null,
            ]);

            return $locked;
        });

        if ($lockedRequest === null) {
            $message = 'This request already has an issued certificate and its status is locked.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 409);
            }

            return back()->withErrors(['status' => $message]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $validated['status'] === 'rejected'
                    ? 'Document request rejected with a recorded reason.'
                    : 'Document request updated successfully.',
                'request' => $lockedRequest->fresh(),
            ]);
        }

        return back()->with('success', 'Document request updated successfully.');
    }

    public function live(): JsonResponse
    {
        Gate::authorize('viewAny', DocumentRequest::class);

        return response()->json(DocumentRequest::query()->latest()->get());
    }

    /**
     * Keep only the requests that sit in one board lane; mirrors getLane() in public/js/admin.js. "Ready to Print"
     * holds only requests that can be issued (ready_for_release, as IssueCertificate requires): an approved request
     * stays in Processing, where "Mark ready" moves it on.
     *
     * @param  Builder<DocumentRequest>  $query
     */
    private function whereLane(Builder $query, string $lane): void
    {
        match ($lane) {
            'completed' => $query->where('status', 'released'),
            'ready' => $query->where('status', 'ready_for_release'),
            'online' => $query->where('status', 'pending')->where('source', 'online'),
            default => $query->whereNotIn('status', ['released', 'ready_for_release', 'rejected'])
                ->where(fn (Builder $query) => $query->where('status', '!=', 'pending')->orWhereNull('source')->orWhere('source', '!=', 'online')),
        };
    }

    /** @param Builder<DocumentRequest> $query */
    private function whereSearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $query->where(fn (Builder $query) => $query
            ->where('full_name', 'like', "%{$search}%")
            ->orWhere('reference_code', 'like', "%{$search}%")
            ->orWhere('document_type', 'like', "%{$search}%"));
    }

    /**
     * Requests per board lane, counted in one grouped query.
     *
     * @return array{online: int, processing: int, ready: int, completed: int}
     */
    private function laneCounts(string $search): array
    {
        $counts = array_fill_keys(self::BOARD_LANES, 0);

        DocumentRequest::query()
            ->where('status', '!=', 'rejected')
            ->tap(fn (Builder $query) => $this->whereSearch($query, $search))
            ->toBase()
            ->select('status', 'source')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('status', 'source')
            ->get()
            ->each(function (object $row) use (&$counts): void {
                $lane = match (true) {
                    $row->status === 'released' => 'completed',
                    $row->status === 'ready_for_release' => 'ready',
                    $row->status === 'pending' && $row->source === 'online' => 'online',
                    default => 'processing',
                };
                $counts[$lane] += (int) $row->aggregate;
            });

        return $counts;
    }
}
