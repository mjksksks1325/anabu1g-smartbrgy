<?php

namespace App\Policies;

use App\Models\Incident;
use App\Models\User;

class IncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['incidents.view', 'vawc.view']);
    }

    public function view(User $user, Incident $incident): bool
    {
        return $user->hasPermission($incident->isRestricted() ? 'vawc.view' : 'incidents.view');
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['incidents.submit', 'vawc.submit']);
    }

    public function update(User $user, Incident $incident): bool
    {
        return $user->hasPermission($incident->isRestricted() ? 'vawc.update' : 'incidents.update');
    }

    public function delete(User $user, Incident $incident): bool
    {
        return $user->hasPermission($incident->isRestricted() ? 'vawc.archive' : 'incidents.archive');
    }

    public function restore(User $user, Incident $incident): bool
    {
        return $this->delete($user, $incident);
    }

    public function forceDelete(User $user, Incident $incident): bool
    {
        return false;
    }
}
