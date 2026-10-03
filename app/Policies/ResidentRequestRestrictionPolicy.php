<?php

namespace App\Policies;

use App\Models\ResidentRequestRestriction;
use App\Models\User;

class ResidentRequestRestrictionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->resident_id === null && in_array($user->role, ['admin', 'staff'], true);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user) && $user->role === 'admin';
    }

    public function update(User $user, ResidentRequestRestriction $restriction): bool
    {
        return $this->create($user);
    }
}
