<?php

namespace App\Console\Commands;

use App\Http\Requests\Admin\SaveUserRequest;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Read-only inventory for access decisions: personnel by role, legacy roles (D5) and who holds explicit VAWC/BPO
 * access (D1). It changes nothing, and prints no email addresses, passwords or case content.
 */
#[Signature('personnel:access-report')]
#[Description('Read-only report of personnel roles, legacy roles and explicit VAWC/BPO access')]
class PersonnelAccessReport extends Command
{
    public function handle(): int
    {
        $personnel = User::query()->whereNull('resident_id')->where('role', '!=', 'resident')->orderBy('id')->get();

        $this->info('Personnel accounts by role');
        $this->table(['Role', 'Active', 'Inactive'], $personnel->groupBy(fn (User $user): string => $this->roleLabel($user))
            ->map(fn ($users, string $role): array => [$role, $users->where('is_active', true)->count(), $users->where('is_active', false)->count()])->values()->all());

        $legacy = $personnel->filter(fn (User $user): bool => $user->role === 'viewer' || ($user->role === 'admin' && ! $user->is_super_admin));
        $this->newLine();
        $this->info('Legacy roles (admin without Super Admin, viewer)');
        if ($legacy->isEmpty()) {
            $this->line('None. The legacy role choices can be removed from filters once production shows the same.');
        } else {
            $this->table(['ID', 'Name', 'Role', 'Active', 'Created'], $legacy->map(fn (User $user): array => [$user->id, $user->name, $user->role, $user->is_active ? 'yes' : 'no', $user->created_at?->toDateString() ?? 'unknown'])->all());
            $this->line('Review each account with the barangay before converting or deactivating it. This report changes nothing.');
        }

        $holders = $personnel->filter(fn (User $user): bool => SaveUserRequest::vawcKeys($user->staff_permissions ?? []) !== []);
        $this->newLine();
        $this->info('Explicit VAWC/BPO assignments');
        $this->table(['ID', 'Name', 'Role', 'Active', 'Keys'], $holders->map(fn (User $user): array => [$user->id, $user->name, $this->roleLabel($user), $user->is_active ? 'yes' : 'no', implode(', ', SaveUserRequest::vawcKeys($user->staff_permissions ?? []))])->values()->all());

        $openRestricted = Incident::query()->whereIn('status', Incident::PENDING_STATUSES)
            ->where(fn ($query) => $query->where('is_sensitive', true)->orWhereRaw("LOWER(incident_type) LIKE '%vawc%'")->orWhereRaw("LOWER(incident_type) LIKE '%bpo%'")
                ->orWhereRaw("LOWER(incident_type) LIKE '%violence against women%'")->orWhereRaw("LOWER(incident_type) LIKE '%domestic violence%'")->orWhereHas('protectionOrders'))
            ->count();
        $ready = $holders->contains(fn (User $user): bool => $user->is_active && $user->canLoginAsPersonnel()
            && in_array('vawc.view', $user->staff_permissions ?? [], true) && in_array('vawc.update', $user->staff_permissions ?? [], true));

        $this->newLine();
        $this->line('Explicit VAWC assignment is '.(User::vawcNeedsExplicitAssignment() ? 'ON' : 'OFF').' (VAWC_EXPLICIT_ASSIGNMENT).');
        $this->line('Open restricted cases: '.$openRestricted);
        $this->line($ready
            ? 'Ready: at least one active account explicitly holds vawc.view and vawc.update. Confirm with the barangay that these are the designated persons before turning it on.'
            : 'Not ready: no active account explicitly holds both vawc.view and vawc.update. Turning it on now would leave restricted cases without a handler.');

        return self::SUCCESS;
    }

    private function roleLabel(User $user): string
    {
        return match (true) {
            $user->role === 'admin' && $user->is_super_admin => 'Super Admin',
            $user->role === 'captain' => 'Barangay Captain',
            $user->role === 'staff' => 'Barangay Staff',
            default => $user->role.' (legacy)',
        };
    }
}
