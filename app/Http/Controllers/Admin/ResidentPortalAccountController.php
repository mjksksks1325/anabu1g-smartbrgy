<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Resident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResidentPortalAccountController extends Controller
{
    public function store(Resident $resident): JsonResponse
    {
        Gate::authorize('update', $resident);

        return DB::transaction(function () use ($resident): JsonResponse {
            $resident = Resident::query()->lockForUpdate()->findOrFail($resident->id);
            if ($resident->status !== 'active' || $resident->portalAccount()->exists()) {
                throw ValidationException::withMessages(['account' => 'Only an active resident without an online account can receive an activation code.']);
            }
            $code = Str::random(32);
            $resident->portal_registration_hash = hash('sha256', $code);
            $resident->portal_registration_expires_at = now()->addDay();
            $resident->portal_registration_email = null;
            $resident->portal_registration_sent_at = null;
            $resident->save();

            return response()->json(['activation_code' => $code, 'expires_at' => $resident->portal_registration_expires_at->toIso8601String(),
                'message' => 'Give this code only to the resident after verifying their identity. It expires in 24 hours and replaces any previous code.']);
        });
    }

    public function update(Request $request, Resident $resident): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);
        $account = $resident->portalAccount()->firstOrFail();
        $account->is_active = $validated['is_active'];
        $account->remember_token = null;
        $account->save();

        return response()->json(['message' => 'Portal account status updated.']);
    }
}
