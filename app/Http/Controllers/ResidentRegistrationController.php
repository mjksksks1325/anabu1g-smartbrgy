<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterResidentAccountRequest;
use App\Http\Requests\VerifyResidentAccountRequest;
use App\Models\Resident;
use App\Models\User;
use App\Notifications\ResidentPortalActivationNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class ResidentRegistrationController extends Controller
{
    public function create(Request $request): View
    {
        if (! $request->session()->pull('resident_registration_continue', false) && ! $request->session()->has('errors')) {
            $request->session()->forget([
                'resident_name_check', 'resident_identity', 'resident_code_sent', 'resident_verification',
                '_old_input',
            ]);
        }

        $resident = $this->verifiedResident($request);
        $identity = $this->identityProof($request);
        $name = $this->nameProof($request);
        $codeSent = $request->session()->get('resident_code_sent');
        $sentAt = is_array($codeSent) ? ($codeSent['sent_at'] ?? null) : $codeSent;
        $codeSent = is_int($sentAt) && $sentAt > now()->subDay()->timestamp;
        $stage = $resident && ! $resident->portalAccount()->exists() ? 'account'
            : ($codeSent ? 'code' : ($identity ? 'email' : ($name ? 'details' : 'name')));

        return view('portal.register', [
            'verified' => $stage === 'account',
            'stage' => $stage,
            'registrationEmail' => $stage === 'account' ? $resident->portal_registration_email : null,
            'emailDeliveryAvailable' => $this->emailDeliveryAvailable(),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $pendingCode = $request->session()->get('resident_code_sent');
        if (is_array($pendingCode) && is_int($pendingCode['resident_id'] ?? null) && is_string($pendingCode['hash'] ?? null)) {
            DB::transaction(function () use ($pendingCode): void {
                $resident = Resident::query()->lockForUpdate()->find($pendingCode['resident_id']);
                if ($resident && is_string($resident->portal_registration_hash)
                    && hash_equals($resident->portal_registration_hash, $pendingCode['hash'])) {
                    $resident->portal_registration_hash = null;
                    $resident->portal_registration_expires_at = null;
                    $resident->portal_registration_email = null;
                    $resident->portal_registration_sent_at = null;
                    $resident->save();
                }
            });
        }
        $request->session()->forget([
            'resident_name_check', 'resident_identity', 'resident_code_sent', 'resident_verification',
            'resident_registration_continue', '_old_input', 'errors',
        ]);

        return redirect()->route('portal.register');
    }

    public function verifyName(Request $request): RedirectResponse
    {
        $request->session()->forget(['resident_name_check', 'resident_identity', 'resident_code_sent', 'resident_verification']);
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'min:3', 'max:255', 'regex:/\A[\pL\pM][\pL\pM\s.\'-]*\z/u'],
        ]);
        $name = Str::lower(Str::squish($validated['full_name']));
        if ($this->matchingResidents($name)->isEmpty()) {
            return redirect()->route('portal.registration.denied');
        }

        $request->session()->put('resident_name_check', ['name' => $name, 'expires_at' => now()->addMinutes(10)->timestamp]);
        $request->session()->flash('resident_registration_continue', true);

        return redirect()->route('portal.register');
    }

    public function confirmRecord(Request $request): RedirectResponse
    {
        $name = $this->nameProof($request);
        if ($name === null) {
            return redirect()->route('portal.register');
        }
        $validated = $request->validate([
            'date_of_birth' => ['required', 'date_format:Y-m-d'],
            'contact_last_four' => ['required', 'digits:4'],
        ]);
        $matches = $this->matchingResidents($name)->filter(function (Resident $resident) use ($validated): bool {
            $contact = preg_replace('/\D+/', '', (string) $resident->contact_number);

            return $resident->date_of_birth->toDateString() === $validated['date_of_birth']
                && strlen($contact) >= 4
                && hash_equals(substr($contact, -4), $validated['contact_last_four']);
        });
        $request->session()->forget('resident_name_check');
        if ($matches->count() !== 1 || $matches->first()->portalAccount()->exists()) {
            return redirect()->route('portal.registration.denied');
        }
        $request->session()->regenerate();
        $request->session()->put('resident_identity', [
            'resident_id' => $matches->first()->id,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);
        $request->session()->flash('resident_registration_continue', true);

        return redirect()->route('portal.register');
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $residentId = $this->identityProof($request);
        if ($residentId === null) {
            return redirect()->route('portal.registration.denied');
        }
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }
        $validated = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);
        $email = $validated['email'];
        if (! $this->emailDeliveryAvailable()) {
            throw ValidationException::withMessages(['email' => 'Email delivery is not available. Please request an activation code from barangay staff.']);
        }

        try {
            $sentHash = DB::transaction(function () use ($residentId, $email): ?string {
                $resident = Resident::query()->lockForUpdate()->find($residentId);
                if (! $resident || $resident->status !== 'active' || $resident->portalAccount()->exists()
                    || $resident->portal_registration_sent_at?->greaterThan(now()->subMinute())) {
                    return null;
                }
                if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                    throw ValidationException::withMessages(['email' => 'This email cannot be used. Sign in or use account recovery if you already have an account.']);
                }
                $code = Str::random(32);
                $hash = hash('sha256', $code);
                $resident->portal_registration_hash = $hash;
                $resident->portal_registration_expires_at = now()->addDay();
                $resident->portal_registration_email = $email;
                $resident->portal_registration_sent_at = now();
                $resident->save();
                Notification::route('mail', $email)->notify(new ResidentPortalActivationNotification($resident->resident_number, $code));

                return $hash;
            });
        } catch (TransportExceptionInterface) {
            throw ValidationException::withMessages(['email' => 'We could not send the activation email. Please try again later or request an activation code from barangay staff.']);
        }
        if ($sentHash === null) {
            return redirect()->route('portal.registration.denied');
        }

        $request->session()->forget('resident_identity');
        $request->session()->put('resident_code_sent', [
            'resident_id' => $residentId,
            'hash' => $sentHash,
            'sent_at' => now()->timestamp,
        ]);
        $request->session()->flash('resident_registration_continue', true);

        return redirect()->route('portal.register')->with('status', 'If the details are eligible, an activation email has been sent. Check your inbox to continue.');
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
        $request->session()->forget(['resident_name_check', 'resident_identity', 'resident_code_sent']);
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
                if ($resident->portal_registration_email !== null && $resident->portal_registration_email !== $email) {
                    throw ValidationException::withMessages(['email' => 'Use the email address that received your activation code.']);
                }
                if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                    throw ValidationException::withMessages(['email' => 'This email cannot be used. Sign in or use account recovery if you already have an account.']);
                }
                $account = new User(['name' => $resident->full_name, 'email' => $email, 'password' => $request->validated('password')]);
                $account->role = 'resident';
                $account->resident()->associate($resident);
                $account->save();
                $resident->portal_registration_email = null;
                $resident->save();
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

    private function nameProof(Request $request): ?string
    {
        $proof = $request->session()->get('resident_name_check');

        return is_array($proof) && is_string($proof['name'] ?? null)
            && is_int($proof['expires_at'] ?? null) && $proof['expires_at'] > now()->timestamp
                ? $proof['name'] : null;
    }

    private function identityProof(Request $request): ?int
    {
        $proof = $request->session()->get('resident_identity');

        return is_array($proof) && is_int($proof['resident_id'] ?? null) && $proof['resident_id'] > 0
            && is_int($proof['expires_at'] ?? null) && $proof['expires_at'] > now()->timestamp
                ? $proof['resident_id'] : null;
    }

    private function emailDeliveryAvailable(): bool
    {
        $transport = config('mail.mailers.'.config('mail.default').'.transport');

        return in_array($transport, ['smtp', 'ses', 'ses-v2', 'postmark', 'resend', 'sendmail'], true);
    }

    /** @return Collection<int, Resident> */
    private function matchingResidents(string $name): Collection
    {
        $firstWord = explode(' ', $name)[0];
        $query = Resident::query()->where('status', 'active');
        if (preg_match('/[^\x00-\x7F]/', $firstWord) !== 1) {
            $query->whereRaw('LOWER(TRIM(first_name)) LIKE ?', [$firstWord.'%']);
        }

        return $query->get(['id', 'first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'contact_number'])
            ->filter(fn (Resident $resident): bool => Str::lower(Str::squish($resident->full_name)) === $name);
    }
}
