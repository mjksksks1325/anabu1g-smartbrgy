<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterResidentAccountRequest;
use App\Http\Requests\VerifyResidentAccountRequest;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ResidentRegistrationController extends Controller
{
    public function create(Request $request): View
    {
        $resident = $this->verifiedResident($request);

        return view('portal.register', ['verified' => $resident !== null && ! $resident->portalAccount()->exists()]);
    }

    public function verify(VerifyResidentAccountRequest $request): RedirectResponse
    {
        $resident = Resident::query()->where('resident_number', $request->validated('resident_number'))->first();
        $hash = hash('sha256', trim($request->validated('activation_code')));
        if (! $resident || $resident->status !== 'active' || ! $resident->portal_registration_expires_at?->isFuture()
            || ! hash_equals($resident->portal_registration_hash ?? '', $hash)) {
            return redirect()->route('portal.registration.denied');
        }
        if ($resident->portalAccount()->exists()) {
            return redirect()->route('portal.registration.denied')->with('existing_account', true);
        }
        $request->session()->regenerate();
        $request->session()->put('resident_verification', [
            'resident_id' => $resident->id, 'hash' => $hash, 'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        return redirect()->route('portal.register');
    }

    public function store(RegisterResidentAccountRequest $request): RedirectResponse
    {
        try {
            $account = DB::transaction(function () use ($request): ?User {
                $resident = $this->verifiedResident($request, true);
                if (! $resident || $resident->portalAccount()->exists()) {
                    return null;
                }
                if (User::query()->whereRaw('LOWER(email) = ?', [$request->validated('email')])->exists()) {
                    throw ValidationException::withMessages(['email' => 'This email cannot be used. Sign in or use account recovery if you already have an account.']);
                }
                $account = new User(['name' => $resident->full_name, 'email' => $request->validated('email'), 'password' => $request->validated('password')]);
                $account->role = 'resident';
                $account->resident()->associate($resident);
                $account->save();
                DB::table('administrative_audits')->insert([
                    'user_id' => $account->id, 'actor' => $account->name, 'action' => 'portal.account.registered',
                    'type' => 'auth', 'record' => null, 'created_at' => now(), 'updated_at' => now(),
                ]);

                return $account;
            });
        } catch (UniqueConstraintViolationException) {
            $account = null;
        }
        $request->session()->forget('resident_verification');
        if (! $account) {
            return redirect()->route('portal.registration.denied');
        }

        return redirect()->route('portal.login')->with('status', 'Your resident account is ready. Sign in to request documents.');
    }

    private function verifiedResident(Request $request, bool $lock = false): ?Resident
    {
        $proof = $request->session()->get('resident_verification');
        if (! is_array($proof) || ! is_int($proof['resident_id'] ?? null) || $proof['resident_id'] < 1
            || ! is_int($proof['expires_at'] ?? null) || $proof['expires_at'] <= now()->timestamp
            || ! is_string($proof['hash'] ?? null)) {
            return null;
        }
        $resident = Resident::query()->when($lock, fn ($query) => $query->lockForUpdate())->find($proof['resident_id']);

        return $resident && $resident->status === 'active' && $resident->portal_registration_expires_at?->isFuture()
            && hash_equals($resident->portal_registration_hash ?? '', $proof['hash']) ? $resident : null;
    }
}
