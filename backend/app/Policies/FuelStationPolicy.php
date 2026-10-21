<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\FuelStation;
use App\Models\User;

class FuelStationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::FuelView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::FuelStationsManage);
    }

    public function update(User $user, FuelStation $station): bool
    {
        return $user->hasPermission(Permission::FuelStationsManage)
            && $user->canAccessOrganization($station->organization_id);
    }

    public function addTransaction(User $user, FuelStation $station): bool
    {
        return $user->hasPermission(Permission::FuelTransactionsCreate)
            && $user->canAccessOrganization($station->organization_id);
    }
}
