<?php

namespace App\Http\Controllers\Admin;

use App\Actions\IssueAdditionalCertificate;
use App\AdditionalCertificateType;
use App\CertificateDocument;
use App\Exceptions\CertificateIssuanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdditionalCertificateRequest;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Staff-side issuance of the further official certificates (AdditionalCertificateType). Staff complete the
 * certificate-specific details, preview the document, and issue it once the barangay has approved its fee.
 */
class AdditionalCertificateController extends Controller
{
    public function index(): View
    {
        Gate::authorize('documents.issue');

        return view('admin.additional-certificates.index', ['types' => AdditionalCertificateType::cases()]);
    }

    public function create(Request $request, string $type): View
    {
        Gate::authorize('documents.issue');
        $certificateType = AdditionalCertificateType::fromSlug($type) ?? abort(404);
        $search = $request->validate([
            'resident_search' => ['nullable', 'string', 'max:100'],
            'partner_search' => ['nullable', 'string', 'max:100'],
        ]);

        return view('admin.additional-certificates.create', [
            'type' => $certificateType,
            'residentSearch' => $search['resident_search'] ?? '',
            'partnerSearch' => $search['partner_search'] ?? '',
            'residents' => $this->matchingResidents($search['resident_search'] ?? null, old('resident_id')),
            'partners' => $certificateType->needsPartner() ? $this->matchingResidents($search['partner_search'] ?? null, old('partner_resident_id')) : null,
        ]);
    }

    /**
     * The certificate as it would print, unsaved: no number, no QR code, a "not valid" watermark, printing disabled.
     * Missing record details and eligibility problems send staff back to the form instead.
     */
    public function preview(AdditionalCertificateRequest $request, IssueAdditionalCertificate $issue, string $type): Response|RedirectResponse
    {
        $certificateType = $request->certificateType();
        $resident = Resident::query()->findOrFail($request->integer('resident_id'));
        $partner = $certificateType->needsPartner() ? Resident::query()->find($request->integer('partner_resident_id')) : null;
        ['certificate' => $certificate, 'problems' => $problems, 'missing' => $missing, 'photo' => $photo] = $issue->prepare($certificateType, $resident, $partner, $request->certificateDetails());

        if ($problems !== [] || $missing !== []) {
            return back()->withInput()->withErrors(['certificate' => [
                ...$problems,
                ...($missing === [] ? [] : ['Complete these details in Resident Records first: '.implode(', ', $missing).'.']),
            ]]);
        }

        $request->flash();
        $disk = Storage::disk('local');
        $photoUrl = $photo === null ? null : 'data:'.$disk->mimeType($photo).';base64,'.base64_encode((string) $disk->get($photo));

        return response()->view($certificateType->printView(), [
            'certificate' => $certificate,
            'document' => new CertificateDocument($certificate, isPreview: true, previewPhotoUrl: $photoUrl),
            'type' => $certificateType,
            'isPreview' => true,
            'backUrl' => route('staff.additional-certificates.create', $certificateType->slug()),
            'backLabel' => 'Back to the form',
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(AdditionalCertificateRequest $request, IssueAdditionalCertificate $issue, string $type): RedirectResponse
    {
        $certificateType = $request->certificateType();

        try {
            $certificate = $issue->issue(
                $certificateType,
                $request->integer('resident_id'),
                $certificateType->needsPartner() ? $request->integer('partner_resident_id') : null,
                $request->certificateDetails(),
                $request->user()->name,
            );
        } catch (CertificateIssuanceException $exception) {
            return back()->withInput()->withErrors(['certificate' => $exception->getMessage()]);
        }

        return redirect()->route('staff.issued-certificates.print', $certificate);
    }

    /**
     * Up to ten active residents matching a name or resident number, plus the one already chosen.
     *
     * @return Collection<int, Resident>
     */
    private function matchingResidents(?string $search, mixed $selectedId): Collection
    {
        $residents = filled($search)
            ? Resident::query()->where('status', 'active')->matchingSearch((string) $search)->orderBy('last_name')->orderBy('first_name')->limit(10)->get()
            : new Collection;

        if (is_numeric($selectedId) && ! $residents->contains('id', (int) $selectedId)) {
            $selected = Resident::query()->find((int) $selectedId);
            if ($selected !== null) {
                $residents->prepend($selected);
            }
        }

        return $residents;
    }
}
