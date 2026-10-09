<?php

namespace App\Policies;

use App\Models\Resident;
use App\Models\User;

class ResidentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['records.view', 'demographics.view', 'households.view']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Resident $resident): bool
    {
        return $user->hasAnyPermission(['records.view', 'demographics.view']);
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
    public function update(User $user, Resident $resident): bool
    {
        return $user->hasPermission('records.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Resident $resident): bool
    {
        return $user->hasPermission('records.archive');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Resident $resident): bool
    {
        return $user->hasPermission('records.archive');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Resident $resident): bool
    {
        return false;
    }
}
