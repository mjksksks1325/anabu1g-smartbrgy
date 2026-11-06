<?php

namespace App\Http\Controllers;

use App\Actions\RecordPortalAccountEvent;
use App\Http\Requests\RegisterResidentAccountRequest;
use App\Http\Requests\VerifyResidentAccountRequest;
use App\Models\Resident;
use App\Models\User;
use App\Notifications\ResidentEmailVerificationNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Resident Portal activation for residents who are already in the barangay database.
 *
 * Identity is proven only by an activation code that authorized staff issue after checking the resident in
 * person (Resident details → Issue activation code). Codes expire after 24 hours, are cleared when used,
 * and are invalidated after MAX_FAILED_ATTEMPTS wrong entries. Every refusal leads to the same generic
 * page, so these steps never reveal whether a resident number exists. After the account is created the
 * resident must confirm their email address before requesting documents (EnsureResidentEmailVerified).
 */
class ResidentRegistrationController extends Controller
{
    public const MAX_FAILED_ATTEMPTS = 5;

    public function __construct(private RecordPortalAccountEvent $events) {}

    public function create(Request $request): View
    {
        if (! $request->session()->pull('resident_registration_continue', false) && ! $request->session()->has('errors')) {
            $request->session()->forget(['resident_verification', '_old_input']);
        }
        $resident = $this->verifiedResident($request);
        $verified = $resident && ! $resident->portalAccount()->exists();

        // Display only: the eligibility explanation comes first; the record is verified only by the staff-issued
        // code in verify(), and the matched record is shown only after that check has passed.
        return view('portal.register', [
            'stage' => match (true) {
                $verified => 'account',
                // ?step=activation comes from the activation-code email (ResidentPortalActivationNotification).
                in_array($request->query('step'), ['verify', 'activation'], true) || $request->session()->has('errors') => 'code',
                default => 'eligibility',
            },
            'verifiedResident' => $verified ? $resident : null,
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->session()->forget(['resident_verification', 'resident_registration_continue', '_old_input', 'errors']);

        return redirect()->route('portal.register');
    }

    public function verify(VerifyResidentAccountRequest $request): RedirectResponse
    {
        $code = trim($request->validated('activation_code'));
        $hash = hash('sha256', strlen($code) === 8 ? Str::upper($code) : $code);

        $outcome = DB::transaction(function () use ($request, $hash): string|Resident {
            $resident = Resident::query()->where('resident_number', $request->validated('resident_number'))->lockForUpdate()->first();
            if (! $resident || $resident->status !== 'active' || $resident->portal_registration_hash === null) {
                return 'denied';
            }
            // Codes from the removed email self-service path carry a send time; staff-issued codes do not.
            if ($resident->portal_registration_sent_at !== null) {
                $this->clearCode($resident);
                $this->events->handle('portal.activation.self-service-code-rejected', $resident->id);

                return 'denied';
            }
            if (! $resident->portal_registration_expires_at?->isFuture()) {
                $this->clearCode($resident);
                $this->events->handle('portal.activation.expired', $resident->id);

                return 'denied';
            }
            if (! hash_equals($resident->portal_registration_hash, $hash)) {
                $key = $this->failureKey($resident);
                Cache::add($key, 0, $resident->portal_registration_expires_at);
                $failures = (int) Cache::increment($key);
                $this->events->handle('portal.activation.failed', $resident->id);
                if ($failures >= self::MAX_FAILED_ATTEMPTS) {
                    Cache::forget($key);
                    $this->clearCode($resident);
                    $this->events->handle('portal.activation.locked', $resident->id);
                }

                return 'denied';
            }
            if ($resident->portalAccount()->exists()) {
                return 'existing';
            }
            Cache::forget($this->failureKey($resident));
            $this->events->handle('portal.activation.verified', $resident->id);

            return $resident;
        });

        if (! $outcome instanceof Resident) {
            $denied = redirect()->route('portal.registration.denied');

            return $outcome === 'existing' ? $denied->with('existing_account', true) : $denied;
        }

        $request->session()->regenerate();
        $request->session()->put('resident_verification', [
            'resident_id' => $outcome->id, 'hash' => $hash, 'expires_at' => now()->addMinutes(10)->timestamp,
        ]);
        $request->session()->flash('resident_registration_continue', true);

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
                $email = $request->validated('email');
                if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                    throw ValidationException::withMessages(['email' => __('This email cannot be used. Sign in or use account recovery if you already have an account.')]);
                }
                $account = new User(['name' => $resident->full_name, 'email' => $email, 'password' => $request->validated('password')]);
                $account->role = 'resident';
                $account->resident()->associate($resident);
                $account->save();
                $this->clearCode($resident);
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

        try {
            $account->notify((new ResidentEmailVerificationNotification)->locale(app()->getLocale()));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('portal.login')
                ->with('status', 'Your resident account is ready, but we could not send the confirmation email. Sign in and select Send a new confirmation link.');
        }

        return redirect()->route('portal.login')
            ->with('status', 'Your resident account is ready. Open the confirmation link we sent to your email, then sign in to request documents.');
    }

    /** A single-use code: cleared when it is used, expires, is rejected, or reaches the attempt limit. */
    private function clearCode(Resident $resident): void
    {
        $resident->forceFill([
            'portal_registration_hash' => null,
            'portal_registration_expires_at' => null,
            'portal_registration_email' => null,
        ])->save();
    }

    private function failureKey(Resident $resident): string
    {
        return 'portal-activation-failures:'.$resident->id.':'.$resident->portal_registration_hash;
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
