<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::VehiclesView);
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $user->hasPermission(Permission::VehiclesView)
            && $user->canAccessOrganization($vehicle->organization_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::VehiclesCreate);
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->hasPermission(Permission::VehiclesUpdate)
            && $user->canAccessOrganization($vehicle->organization_id);
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->hasPermission(Permission::VehiclesDelete)
            && $user->canAccessOrganization($vehicle->organization_id);
    }
}
