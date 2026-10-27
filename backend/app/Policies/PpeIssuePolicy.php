<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Employee;
use App\Models\PpeIssue;
use App\Models\User;

class PpeIssuePolicy
{
    public function viewFor(User $user, Employee $employee): bool
    {
        return $user->hasPermission(Permission::OhsView) && $user->canAccessOrganization($employee->organization_id);
    }

    public function createFor(User $user, Employee $employee): bool
    {
        return $user->hasPermission(Permission::OhsPpeManage) && $user->canAccessOrganization($employee->organization_id);
    }

    public function close(User $user, PpeIssue $issue): bool
    {
        return $this->createFor($user, $issue->employee);
    }
}
