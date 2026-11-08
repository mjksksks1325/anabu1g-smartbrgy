<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('portal:unverified-accounts {--all : List every resident account, not only those needing review}')]
#[Description('List resident portal accounts whose identity was not checked by staff (read only; no names or emails)')]
class ListUnverifiedPortalAccounts extends Command
{
    public function handle(): int
    {
        $rows = User::query()->where('role', 'resident')->with('resident')->orderBy('id')->get()
            ->map(fn (User $account): array => [
                'account' => $account->id,
                'resident_number' => $account->resident->resident_number ?? '—',
                'activation' => $account->portalActivationPath(),
                'email_confirmed' => $account->email_verified_at !== null ? 'yes' : 'no',
                'status' => $account->is_active ? 'active' : 'suspended',
                'created' => $account->created_at?->toDateString() ?? '—',
            ]);
        $needsReview = $rows->where('activation', '!=', 'staff_code');

        $this->info("Resident accounts: {$rows->count()}; staff-issued code: {$rows->where('activation', 'staff_code')->count()}; email self-service: {$rows->where('activation', 'email_self_service')->count()}; not recorded: {$rows->where('activation', 'unknown')->count()}.");
        $listed = $this->option('all') ? $rows : $needsReview;
        if ($listed->isEmpty()) {
            $this->info('No accounts need identity review.');

            return self::SUCCESS;
        }
        $this->table(['Account', 'Resident number', 'Activated with', 'Email confirmed', 'Status', 'Created'], $listed->values()->all());
        $this->line('Review each listed account in Resident details: check the resident\'s valid ID in person, and suspend the account if identity cannot be confirmed. Nothing is changed by this command.');

        return self::SUCCESS;
    }
}
