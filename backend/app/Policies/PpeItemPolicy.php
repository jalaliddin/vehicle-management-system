<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\PpeItem;
use App\Models\User;

class PpeItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(Permission::EmployeesView, Permission::OhsView, Permission::OrgStructureManage);
    }

    public function view(User $user, PpeItem $ppeItem): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::OhsPpeManage);
    }

    public function update(User $user, PpeItem $ppeItem): bool
    {
        return $user->hasPermission(Permission::OhsPpeManage);
    }

    public function delete(User $user, PpeItem $ppeItem): bool
    {
        return $user->hasPermission(Permission::OhsPpeManage);
    }
}
