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

    public function view(User $user, FuelStation $station): bool
    {
        return $user->hasPermission(Permission::FuelView) && $user->canAccessStation($station);
    }

    public function update(User $user, FuelStation $station): bool
    {
        return $user->hasPermission(Permission::FuelStationsManage) && $user->canAccessStation($station);
    }

    /**
     * Run shifts and record receipts and refuels at the station.
     */
    public function operate(User $user, FuelStation $station): bool
    {
        return $user->hasPermission(Permission::FuelTransactionsCreate) && $user->canAccessStation($station);
    }

    public function adjust(User $user, FuelStation $station): bool
    {
        return $user->hasPermission(Permission::FuelAdjust) && $user->canAccessStation($station);
    }

    public function addTransaction(User $user, FuelStation $station): bool
    {
        return $this->operate($user, $station);
    }
}
