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
            ->where('role', 'admin')
            ->where('is_active', true)
            ->where('is_super_admin', true)
            ->whereNull('resident_id')
            ->get(['password'])
            ->contains(fn (User $superAdmin): bool => Hash::check($password, $superAdmin->password));
    }
}
