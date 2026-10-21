<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::EmployeesView);
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->hasPermission(Permission::EmployeesView)
            && $user->canAccessOrganization($employee->organization_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::EmployeesManage);
    }

    /**
     * Edit the card and record employment events and absences.
     */
    public function update(User $user, Employee $employee): bool
    {
        return $user->hasPermission(Permission::EmployeesManage)
            && $user->canAccessOrganization($employee->organization_id);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $this->update($user, $employee);
    }

    public function viewHistory(User $user, Employee $employee): bool
    {
        return $user->hasPermission(Permission::AuditView) && $this->view($user, $employee);
    }
}
