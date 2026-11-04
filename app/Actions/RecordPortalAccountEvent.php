<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Audit entries for Resident Portal activation and verification. They record the event, the resident
 * record id and, once it exists, the account; never activation codes, links, names or email addresses.
 */
class RecordPortalAccountEvent
{
    public function handle(string $action, ?int $residentId = null, ?User $account = null): void
    {
        DB::table('administrative_audits')->insert([
            'user_id' => $account?->getKey(),
            'actor' => $account?->name ?? 'Resident Portal',
            'action' => $action,
            'type' => 'auth',
            'record' => $residentId !== null ? 'resident:'.$residentId : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
