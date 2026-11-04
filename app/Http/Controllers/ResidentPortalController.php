<?php

namespace App\Http\Controllers;

use App\Actions\CheckRequestRestrictions;
use App\CertificateType;
use App\Models\Announcement;
use App\Models\Incident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResidentPortalController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if ($request->boolean('request')) {
            return redirect()->route('portal.request.create', ['service' => $request->string('service')->toString()]);
        }

        return view('portal.index', [
            'services' => CertificateType::cases(),
            'announcements' => Announcement::published()->orderByDesc('published_at')->orderByDesc('id')->limit(3)->get(),
        ]);
    }

    public function announcements(): View
    {
        return view('portal.announcements', [
            'announcements' => Announcement::published()
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate(12),
        ]);
    }

    public function createRequest(Request $request): View
    {
        $residentId = $request->user()->resident_id;
        $hasPendingOrdinaryIncidentReport = $residentId !== null && Incident::query()
            ->ordinary()
            ->where('respondent_resident_id', $residentId)
            ->whereIn('status', Incident::PENDING_STATUSES)
            ->exists();

        $resident = $request->user()->resident;

        return view('portal.request', [
            'services' => CertificateType::cases(),
            'hasPendingOrdinaryIncidentReport' => $hasPendingOrdinaryIncidentReport,
            'requestHolds' => $resident ? app(CheckRequestRestrictions::class)->allActive($resident)->map->residentNotice() : collect(),
        ]);
    }

    public function login(Request $request): View
    {
        $service = $request->string('service')->toString();
        if (in_array($service, array_map(fn (CertificateType $type): string => $type->portalCode(), CertificateType::cases()), true)) {
            $request->session()->put('portal_service', $service);
            $request->session()->put('portal_login_destination', 'request');
        }
        if ($request->query('next') === 'request') {
            $request->session()->put('portal_login_destination', 'request');
        }

        return view('portal.login');
    }

    public function information(): View
    {
        return view('portal.information', [
            'services' => CertificateType::cases(),
            'resources' => config('portal.resources', []),
        ]);
    }

    public function officials(): View
    {
        return view('portal.officials', ['officials' => config('portal.officials', [])]);
    }

    public function account(Request $request): View
    {
        $resident = $request->user()->resident;

        return view('portal.account', ['resident' => $resident, 'requests' => $resident->documentRequests()->latest()->paginate(15)]);
    }

    public function dashboard(Request $request): View
    {
        $resident = $request->user()->resident;

        return view('portal.dashboard', [
            'resident' => $resident,
            'statusCounts' => $resident->documentRequests()->toBase()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
            'attentionRequests' => $resident->documentRequests()
                ->where(fn ($query) => $query
                    ->where('status', 'ready_for_release')
                    ->orWhere(fn ($query) => $query->where('status', 'rejected')->where('updated_at', '>=', now()->subDays(30)))
                    ->orWhere(fn ($query) => $query->whereIn('status', ['pending', 'processing', 'approved'])->whereNotNull('remarks')->where('remarks', '!=', '')))
                ->latest()->latest('id')->limit(5)->get(),
            'recentRequests' => $resident->documentRequests()->latest()->latest('id')->limit(5)->get(),
            'announcements' => Announcement::published()->orderByDesc('published_at')->orderByDesc('id')->limit(3)->get(),
        ]);
    }

    /**
     * "My account" and the profile are one page; this address stays so older links keep working.
     */
    public function accountHome(): RedirectResponse
    {
        return redirect()->route('portal.profile');
    }

    public function showRequest(Request $request, string $referenceCode): View
    {
        $documentRequest = $request->user()->resident->documentRequests()
            ->where('reference_code', $referenceCode)
            ->firstOrFail();

        return view('portal.request-status', [
            'item' => $documentRequest,
            'certificateType' => CertificateType::tryFromLabel($documentRequest->document_type),
        ]);
    }

    public function requestStatuses(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:15'],
            'ids.*' => ['required', 'integer', 'distinct', 'min:1'],
        ]);

        $requests = $request->user()->resident->documentRequests()
            ->whereIn('id', $validated['ids'])
            ->get(['id', 'reference_code', 'document_type', 'created_at', 'status', 'remarks', 'rejection_reason']);

        return response()->json(['items' => $requests->mapWithKeys(fn ($item): array => [
            $item->id => [
                'version' => $item->historyVersion(),
                'html' => view('portal.partials.request-history-item', ['item' => $item])->render(),
            ],
        ])]);
    }

    public function profile(Request $request): View
    {
        return view('portal.profile', ['resident' => $request->user()->resident]);
    }

    public function identity(Request $request): JsonResponse
    {
        $resident = $request->user()->resident;

        return response()->json(['name' => $resident->full_name, 'address' => $resident->address, 'dob' => $resident->date_of_birth->toDateString(), 'email' => $request->user()->email]);
    }
}
