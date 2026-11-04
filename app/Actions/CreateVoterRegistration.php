<?php

namespace App\Actions;

use App\Models\Resident;
use App\Models\VoterRegistration;
use App\Models\VoterRegistrationAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateVoterRegistration
{
    /** @param array<string, mixed> $validated */
    public function handle(Request $request, array $validated): VoterRegistration
    {
        return DB::transaction(function () use ($request, $validated): VoterRegistration {
            $resident = Resident::query()->whereKey($validated['resident_id'])->lockForUpdate()->firstOrFail();
            $identifier = $validated['comelec_voter_number'];
            $identifierHash = VoterRegistration::identifierHash($identifier);

            if (VoterRegistration::query()->where('resident_id', $resident->id)->exists()) {
                throw ValidationException::withMessages(['resident_id' => 'This resident is already registered in the voters list.']);
            }
            if (VoterRegistration::query()->where('comelec_voter_number_hash', $identifierHash)->exists()) {
                throw ValidationException::withMessages(['comelec_voter_number' => 'This COMELEC voter number is already registered.']);
            }
            if ($resident->date_of_birth->diffInYears(Carbon::parse($validated['registration_date'])) < 15) {
                throw ValidationException::withMessages(['resident_id' => 'The resident must be at least 15 years old on the registration date.']);
            }
            if ($resident->status !== 'active') {
                throw ValidationException::withMessages(['resident_id' => 'Only active residents can be added to the voters list.']);
            }

            $registration = VoterRegistration::query()->create([
                ...$validated,
                'comelec_voter_number_hash' => $identifierHash,
                'created_by' => $request->user()->id,
            ]);
            $key = hash('sha256', (string) config('app.key'));
            VoterRegistrationAudit::query()->create([
                'voter_registration_id' => $registration->id,
                'actor_id' => $request->user()->id,
                'action' => 'created',
                'changes' => [
                    'resident_id' => $resident->id,
                    'voter_number' => $registration->voter_number,
                    'masked_comelec_voter_number' => $registration->masked_comelec_voter_number,
                    'status' => $registration->status,
                ],
                'ip_hash' => $request->ip() ? hash_hmac('sha256', $request->ip(), $key) : null,
                'user_agent_hash' => $request->userAgent() ? hash_hmac('sha256', $request->userAgent(), $key) : null,
            ]);

            return $registration;
        });
    }
}
