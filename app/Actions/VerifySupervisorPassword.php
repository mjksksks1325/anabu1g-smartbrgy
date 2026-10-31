<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class VerifySupervisorPassword
{
    public function passes(string $password): bool
    {
        if ($password === '' || strlen($password) > 255) {
            return false;
        }

        return User::query()
            ->where('is_active', true)
            ->whereNull('resident_id')
            ->where(function ($query): void {
                $query->where(function ($superAdmins): void {
                    $superAdmins->where('role', 'admin')->where('is_super_admin', true);
                })->orWhere('role', 'captain');
            })
            ->get(['password'])
            ->contains(fn (User $approver): bool => Hash::check($password, $approver->password));
    }
}
