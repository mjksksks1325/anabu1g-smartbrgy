<?php

namespace App\Policies;

use App\Models\Resident;
use App\Models\User;

class ResidentPolicy
{
    /**
     * Determine whether the user can list residents or households. Demographics-only staff see population statistics, not people.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['records.view', 'households.view']);
    }

    /**
     * Determine whether the user can open one resident's record or photo.
     */
    public function view(User $user, Resident $resident): bool
    {
        return $user->hasPermission('records.view');
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
