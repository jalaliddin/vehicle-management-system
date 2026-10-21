<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(Permission::EmployeesView, Permission::OhsView, Permission::OrgStructureManage);
    }

    public function view(User $user, Department $department): bool
    {
        return $this->viewAny($user) && $user->canAccessOrganization($department->organization_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::OrgStructureManage);
    }

    public function update(User $user, Department $department): bool
    {
        return $user->hasPermission(Permission::OrgStructureManage)
            && $user->canAccessOrganization($department->organization_id);
    }

    public function delete(User $user, Department $department): bool
    {
        return $this->update($user, $department);
    }
}
