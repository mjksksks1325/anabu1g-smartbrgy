<?php

namespace App\Policies;

use App\Models\Purok;
use App\Models\User;

class PurokPolicy
{
    private function isStaff(User $user): bool
    {
        return in_array($user->role, ['admin', 'staff'], true);
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->isStaff($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Purok $purok): bool
    {
        return $this->isStaff($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->isStaff($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Purok $purok): bool
    {
        return $this->isStaff($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Purok $purok): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Purok $purok): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Purok $purok): bool
    {
        return false;
    }
}
