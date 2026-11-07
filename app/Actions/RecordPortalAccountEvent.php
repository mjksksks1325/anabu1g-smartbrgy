<?php

namespace App\Actions;

use App\Exceptions\MailDeliveryUnavailableException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

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

    /**
     * Record that an email confirmation link was not sent: `confirmation-mail-unavailable` when this server does not
     * deliver email, `confirmation-mail-failed` (and reported) when the mail server refused or could not be reached.
     */
    public function confirmationMailNotSent(Throwable $exception, User $account): void
    {
        $unavailable = $exception instanceof MailDeliveryUnavailableException;
        if (! $unavailable) {
            report($exception);
        }
        $this->handle($unavailable ? 'portal.email.confirmation-mail-unavailable' : 'portal.email.confirmation-mail-failed', $account->resident_id, $account);
    }
}
