<?php

namespace App\Http\Controllers\Admin;

use App\Actions\StoreCertificateEvidence;
use App\AdditionalCertificateType;
use App\Http\Controllers\Controller;
use App\Models\CertificateEvidence;
use App\Models\Resident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Evidence photographs for certificates that carry them (the Probation certificate's community-service page).
 * Uploading and removing need documents.issue; viewing needs documents.issue, or documents.print once the photograph
 * belongs to an issued certificate. Files are served only through this controller, never from a public folder.
 */
class CertificateEvidenceController extends Controller
{
    public function store(Request $request, StoreCertificateEvidence $storeEvidence, string $type, Resident $resident): RedirectResponse
    {
        Gate::authorize('documents.issue');
        $certificateType = AdditionalCertificateType::fromSlug($type);
        abort_unless($certificateType?->acceptsEvidence() === true, 404);

        $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:'.CertificateEvidence::MAX_PER_CERTIFICATE],
            'photos.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=400,min_height=400,max_width=8000,max_height=8000'],
        ], [], ['photos' => 'photographs', 'photos.*' => 'photograph']);

        $stored = [];
        try {
            DB::transaction(function () use ($request, $storeEvidence, $certificateType, $resident, &$stored): void {
                Resident::query()->lockForUpdate()->findOrFail($resident->id);
                $existing = CertificateEvidence::query()->draftFor($resident, $certificateType->value)->count();
                if ($existing + count($request->file('photos')) > CertificateEvidence::MAX_PER_CERTIFICATE) {
                    throw ValidationException::withMessages(['photos' => __('A certificate holds at most :max photographs. Remove one before adding more.', ['max' => CertificateEvidence::MAX_PER_CERTIFICATE])]);
                }
                foreach ($request->file('photos') as $file) {
                    $stored[] = $storeEvidence->handle($file, $resident, $certificateType, $request->user());
                }
            });
        } catch (Throwable $exception) {
            foreach ($stored as $evidence) {
                Storage::disk('local')->delete($evidence->path);
            }

            throw $exception;
        }

        return redirect()->route('staff.additional-certificates.create', [$certificateType->slug(), 'resident_id' => $resident->id])
            ->with('status', trans_choice(':count photograph added.|:count photographs added.', count($stored)));
    }

    public function show(CertificateEvidence $certificateEvidence): StreamedResponse
    {
        abort_unless(
            Gate::allows('documents.issue') || (! $certificateEvidence->isDraft() && Gate::allows('documents.print')),
            403,
        );
        $disk = Storage::disk('local');
        abort_unless($disk->exists($certificateEvidence->path), 404);

        return $disk->response($certificateEvidence->path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }

    /**
     * Removes a draft photograph. Photographs that belong to an issued certificate are kept with it.
     */
    public function destroy(CertificateEvidence $certificateEvidence): RedirectResponse
    {
        Gate::authorize('documents.issue');

        $removed = DB::transaction(function () use ($certificateEvidence): bool {
            $evidence = CertificateEvidence::query()->lockForUpdate()->findOrFail($certificateEvidence->id);
            if (! $evidence->isDraft()) {
                return false;
            }
            $evidence->delete();
            DB::afterCommit(fn () => Storage::disk('local')->delete($evidence->path));

            return true;
        });

        $type = AdditionalCertificateType::tryFrom($certificateEvidence->certificate_type);
        $redirect = redirect()->route('staff.additional-certificates.create', [$type?->slug() ?? 'probation', 'resident_id' => $certificateEvidence->resident_id]);

        return $removed
            ? $redirect->with('status', __('Photograph removed.'))
            : $redirect->withErrors(['photos' => __('This photograph belongs to an issued certificate and cannot be removed.')]);
    }
}
