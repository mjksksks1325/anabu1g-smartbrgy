<?php

namespace App\Http\Controllers\Admin;

use App\CertificateType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreResidentRequest;
use App\Http\Requests\Admin\UpdateResidentRequest;
use App\Models\DocumentRequest;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResidentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Resident::class);

        $query = $this->residentQuery($request)
            ->withCount(['documentRequests', 'issuedCertificates']);

        $residents = $query->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return response()->json($residents);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Resident::class);
        $residents = $this->residentQuery($request);

        return response()->streamDownload(function () use ($residents): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fputcsv($output, [
                'Resident ID', 'Full Name', 'Date of Birth', 'Gender', 'Civil Status',
                'Purok', 'Address', 'Contact Number', 'Residency Type', 'Status',
            ]);

            foreach ($residents->cursor() as $resident) {
                fputcsv($output, array_map($this->csvValue(...), [
                    $resident->resident_number,
                    $resident->full_name,
                    $resident->date_of_birth->toDateString(),
                    $resident->gender,
                    $resident->civil_status,
                    $resident->purok,
                    $resident->address,
                    $resident->contact_number,
                    $resident->residency_type,
                    $resident->trashed() ? 'archived' : $resident->status,
                ]));
            }

            fclose($output);
        }, 'resident-records-'.today()->toDateString().'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreResidentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->rejectDuplicate($validated, $request->boolean('confirm_duplicate'));

        $resident = Resident::query()->create(Arr::except($validated, 'confirm_duplicate'));

        return response()->json([
            'message' => 'Resident registered successfully.',
            'resident' => $resident,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Resident $resident): JsonResponse
    {
        Gate::authorize('view', $resident);

        return response()->json($resident
            ->load([
                'documentRequests' => fn ($query) => $query->latest()->limit(50),
                'issuedCertificates' => fn ($query) => $query->latest('issued_at')->limit(50),
            ])
            ->loadCount(['documentRequests', 'issuedCertificates']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateResidentRequest $request, Resident $resident): JsonResponse
    {
        $validated = $request->validated();
        $this->rejectDuplicate($validated, $request->boolean('confirm_duplicate'), $resident);

        $resident->update(Arr::except($validated, 'confirm_duplicate'));

        return response()->json([
            'message' => 'Resident record updated successfully.',
            'resident' => $resident->fresh(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Resident $resident): JsonResponse
    {
        Gate::authorize('delete', $resident);
        $resident->delete();

        return response()->json(['message' => 'Resident record archived successfully.']);
    }

    public function restore(int $resident): JsonResponse
    {
        $residentRecord = Resident::query()->onlyTrashed()->findOrFail($resident);
        Gate::authorize('restore', $residentRecord);
        $residentRecord->restore();

        return response()->json([
            'message' => 'Resident record restored successfully.',
            'resident' => $residentRecord->fresh(),
        ]);
    }

    public function eligibility(Request $request, Resident $resident): JsonResponse
    {
        Gate::authorize('view', $resident);
        $validated = $request->validate([
            'certificate_type' => ['required', Rule::in(CertificateType::values())],
        ]);
        $certificateType = CertificateType::from($validated['certificate_type']);

        return response()->json([
            'resident' => $resident,
            'certificate_type' => $certificateType->value,
            ...$resident->certificateEligibility($certificateType),
        ]);
    }

    public function requestRecords(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Resident::class);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'eligibility' => ['nullable', Rule::in(['eligible', 'ineligible'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $residents = Resident::query()
            ->with(['documentRequests' => fn ($query) => $query->latest()->limit(50)])
            ->when($validated['search'] ?? null, function (Builder $query, string $search): void {
                $term = '%'.trim($search).'%';
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('resident_number', 'like', $term)
                        ->orWhere('first_name', 'like', $term)
                        ->orWhere('middle_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term);
                });
            })
            ->when(($validated['eligibility'] ?? null) === 'eligible', fn (Builder $query) => $query
                ->where('status', 'active')
                ->where('is_in_good_standing', true))
            ->when(($validated['eligibility'] ?? null) === 'ineligible', fn (Builder $query) => $query
                ->where(function (Builder $query): void {
                    $query->where('status', '!=', 'active')->orWhere('is_in_good_standing', false);
                }))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate((int) ($validated['per_page'] ?? 15));

        return response()->json([
            ...$residents->toArray(),
            'summary' => [
                'total_requests' => DocumentRequest::query()->count(),
                'completed' => DocumentRequest::query()->where('status', 'released')->count(),
                'blocked' => DocumentRequest::query()->where('status', 'rejected')->count(),
                'needs_review' => Resident::query()->where('is_in_good_standing', false)->count(),
            ],
        ]);
    }

    public function requestRecordsExport(): StreamedResponse
    {
        Gate::authorize('viewAny', Resident::class);

        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fputcsv($output, ['Reference Code', 'Resident ID', 'Resident', 'Document', 'Source', 'Status', 'Requested At']);
            foreach (DocumentRequest::query()->with('resident')->latest()->cursor() as $documentRequest) {
                fputcsv($output, array_map($this->csvValue(...), [
                    $documentRequest->reference_code,
                    $documentRequest->resident?->resident_number,
                    $documentRequest->full_name,
                    $documentRequest->document_type,
                    $documentRequest->source,
                    $documentRequest->status,
                    $documentRequest->created_at?->toDateTimeString(),
                ]));
            }

            fclose($output);
        }, 'request-records-'.today()->toDateString().'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function rejectDuplicate(
        array $attributes,
        bool $duplicateConfirmed,
        ?Resident $ignoredResident = null,
    ): void {
        if ($duplicateConfirmed) {
            return;
        }

        $duplicate = Resident::query()
            ->whereRaw('LOWER(first_name) = ?', [mb_strtolower($attributes['first_name'])])
            ->whereRaw('LOWER(last_name) = ?', [mb_strtolower($attributes['last_name'])])
            ->whereDate('date_of_birth', $attributes['date_of_birth'])
            ->when($ignoredResident !== null, fn (Builder $query) => $query->whereKeyNot($ignoredResident->getKey()))
            ->first();

        if ($duplicate !== null) {
            throw ValidationException::withMessages([
                'duplicate' => "Possible duplicate: {$duplicate->full_name} ({$duplicate->resident_number}). Review the existing record or confirm the duplicate.",
            ]);
        }
    }

    /** @return Builder<Resident> */
    private function residentQuery(Request $request): Builder
    {
        $status = $request->string('status')->toString();
        $query = $status === 'archived'
            ? Resident::query()->onlyTrashed()
            : Resident::query();

        return $query
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = '%'.$request->string('search')->trim()->toString().'%';

                $query->where(function (Builder $query) use ($search): void {
                    $query->where('resident_number', 'like', $search)
                        ->orWhere('first_name', 'like', $search)
                        ->orWhere('middle_name', 'like', $search)
                        ->orWhere('last_name', 'like', $search)
                        ->orWhere('purok', 'like', $search);
                });
            })
            ->when(in_array($status, ['active', 'inactive'], true), fn (Builder $query) => $query->where('status', $status))
            ->when($status === 'senior', fn (Builder $query) => $query->whereDate('date_of_birth', '<=', today()->subYears(60)))
            ->when($request->filled('purok'), fn (Builder $query) => $query->where('purok', $request->string('purok')->toString()))
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    private function csvValue(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'{$value}" : $value;
    }
}
