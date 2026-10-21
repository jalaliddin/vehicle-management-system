<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Position;
use App\Models\User;

class PositionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(Permission::EmployeesView, Permission::OhsView, Permission::OrgStructureManage);
    }

    public function view(User $user, Position $position): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::OrgStructureManage);
    }

    public function update(User $user, Position $position): bool
    {
        return $user->hasPermission(Permission::OrgStructureManage);
    }

    public function delete(User $user, Position $position): bool
    {
        return $user->hasPermission(Permission::OrgStructureManage);
    }
}
