<?php

namespace App\Http\Controllers;

use App\CertificateType;
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

        return view('portal.index', ['services' => CertificateType::cases()]);
    }

    public function createRequest(): View
    {
        return view('portal.request', ['services' => CertificateType::cases()]);
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
