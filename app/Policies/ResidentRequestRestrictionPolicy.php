<?php

namespace App\Policies;

use App\Models\ResidentRequestRestriction;
use App\Models\User;

class ResidentRequestRestrictionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['eligibility.view', 'documents.view']);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, ResidentRequestRestriction $restriction): bool
    {
        return $this->create($user);
    }
}
