<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Waybill;

/**
 * Who may act on a waybill. Whether its current status allows the action is
 * checked separately by the controller.
 */
class WaybillPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::WaybillsView);
    }

    public function view(User $user, Waybill $waybill): bool
    {
        return $this->allows($user, $waybill, Permission::WaybillsView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::WaybillsCreate);
    }

    public function update(User $user, Waybill $waybill): bool
    {
        return $this->allows($user, $waybill, Permission::WaybillsUpdate);
    }

    public function delete(User $user, Waybill $waybill): bool
    {
        return $this->allows($user, $waybill, Permission::WaybillsDelete);
    }

    public function submit(User $user, Waybill $waybill): bool
    {
        return $this->allows($user, $waybill, Permission::WaybillsSubmit);
    }

    public function mechanicCheck(User $user, Waybill $waybill): bool
    {
        return $this->allows($user, $waybill, Permission::WaybillsMechanicCheck);
    }

    public function doctorCheck(User $user, Waybill $waybill): bool
    {
        return $this->allows($user, $waybill, Permission::WaybillsDoctorCheck);
    }

    public function approve(User $user, Waybill $waybill): bool
    {
        return $this->allows($user, $waybill, Permission::WaybillsApprove);
    }

    public function depart(User $user, Waybill $waybill): bool
    {
        return $this->allows($user, $waybill, Permission::WaybillsDepart);
    }

    public function complete(User $user, Waybill $waybill): bool
    {
        return $this->allows($user, $waybill, Permission::WaybillsComplete);
    }

    private function allows(User $user, Waybill $waybill, Permission $permission): bool
    {
        return $user->hasPermission($permission)
            && $user->canAccessOrganization($waybill->organization_id);
    }
}
