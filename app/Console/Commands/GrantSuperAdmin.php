<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('user:grant-super-admin {email : Existing administrator email} {--revoke : Remove Super Admin access}')]
#[Description('Grant or revoke Super Admin access for an existing administrator account')]
class GrantSuperAdmin extends Command
{
    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            $this->error('No account matches that email. Use the email of an existing administrator. To create the first account, run user:create-first-super-admin.');

            return self::FAILURE;
        }

        if ($user->role !== 'admin' || $user->resident_id !== null) {
            $this->error('That account is not an administrator. To create the first account, run user:create-first-super-admin.');

            return self::FAILURE;
        }

        $revoke = (bool) $this->option('revoke');
        if (! $revoke && ! $user->is_active) {
            $this->error('Activate the administrator account before granting Super Admin access.');

            return self::FAILURE;
        }

        if ($revoke && $user->is_super_admin && User::query()->where('is_super_admin', true)->where('is_active', true)->count() <= 1) {
            $this->error('The last active Super Admin cannot be revoked.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($user, $revoke): void {
            $user->forceFill(['is_super_admin' => ! $revoke])->save();
            DB::table('administrative_audits')->insert([
                'user_id' => $user->id,
                'actor' => 'Local operator',
                'action' => $revoke ? 'security.super-admin.revoked' : 'security.super-admin.granted',
                'type' => 'security',
                'record' => (string) $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->info($revoke ? 'Super Admin access revoked.' : 'Super Admin access granted.');

        return self::SUCCESS;
    }
}
