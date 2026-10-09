<?php

namespace App\Policies;

use App\Models\Purok;
use App\Models\User;

class PurokPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['demographics.view', 'records.view', 'households.view', 'voters.view', 'documents.view']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Purok $purok): bool
    {
        return $user->hasPermission('demographics.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('records.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Purok $purok): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Purok $purok): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Purok $purok): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Purok $purok): bool
    {
        return false;
    }
}
