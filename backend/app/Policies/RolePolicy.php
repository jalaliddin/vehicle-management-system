<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * Editing roles is reserved for Super Admin, who passes every check through
 * the Gate::before hook; everyone else is denied here.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::UsersManage);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Role $role): bool
    {
        return false;
    }

    public function delete(User $user, Role $role): bool
    {
        return false;
    }

    /**
     * Whether the user may give this role to someone.
     */
    public function assign(User $user, Role $role): bool
    {
        return $user->hasPermission(Permission::UsersManage) && $user->holdsAllPermissionsOf($role);
    }
}
