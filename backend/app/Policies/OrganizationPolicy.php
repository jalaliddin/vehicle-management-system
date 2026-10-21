<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    /**
     * The organization list is reference data every form needs.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->canAccessOrganization($organization->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::OrganizationsManage);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->hasPermission(Permission::OrganizationsManage);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->hasPermission(Permission::OrganizationsManage);
    }
}
