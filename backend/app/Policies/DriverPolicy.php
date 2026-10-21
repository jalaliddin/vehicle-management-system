<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Driver;
use App\Models\User;

class DriverPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::DriversView);
    }

    public function view(User $user, Driver $driver): bool
    {
        return $user->hasPermission(Permission::DriversView)
            && $user->canAccessOrganization($driver->organization_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::DriversCreate);
    }

    public function update(User $user, Driver $driver): bool
    {
        return $user->hasPermission(Permission::DriversUpdate)
            && $user->canAccessOrganization($driver->organization_id);
    }

    public function delete(User $user, Driver $driver): bool
    {
        return $user->hasPermission(Permission::DriversDelete)
            && $user->canAccessOrganization($driver->organization_id);
    }
}
