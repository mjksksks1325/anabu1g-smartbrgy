<?php

namespace App\Policies;

use App\Models\BarangayProtectionOrder;
use App\Models\User;

class BarangayProtectionOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('vawc.view');
    }

    public function view(User $user, BarangayProtectionOrder $order): bool
    {
        return $user->hasPermission('vawc.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('vawc.submit');
    }

    public function update(User $user, BarangayProtectionOrder $order): bool
    {
        return $user->hasPermission('vawc.update');
    }
}
