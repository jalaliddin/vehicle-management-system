<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\FuelSupplier;
use App\Models\User;

class FuelSupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::FuelView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::FuelStationsManage);
    }

    public function update(User $user, FuelSupplier $supplier): bool
    {
        return $user->hasPermission(Permission::FuelStationsManage);
    }

    public function delete(User $user, FuelSupplier $supplier): bool
    {
        return $user->hasPermission(Permission::FuelStationsManage);
    }
}
