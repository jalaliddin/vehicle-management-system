<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Employee;
use App\Models\MedicalExam;
use App\Models\User;

class MedicalExamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(Permission::OhsView, Permission::OhsMedicalView);
    }

    public function viewFor(User $user, Employee $employee): bool
    {
        return $this->viewAny($user) && $user->canAccessOrganization($employee->organization_id);
    }

    public function createFor(User $user, Employee $employee): bool
    {
        return $user->hasPermission(Permission::OhsMedicalManage) && $user->canAccessOrganization($employee->organization_id);
    }

    public function update(User $user, MedicalExam $exam): bool
    {
        return $this->createFor($user, $exam->employee);
    }

    public function delete(User $user, MedicalExam $exam): bool
    {
        return $this->createFor($user, $exam->employee);
    }

    public function viewDocument(User $user, MedicalExam $exam): bool
    {
        return $user->hasPermission(Permission::OhsMedicalView) && $user->canAccessOrganization($exam->employee->organization_id);
    }
}
