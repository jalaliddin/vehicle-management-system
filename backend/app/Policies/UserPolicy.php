<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::UsersView) || $user->hasPermission(Permission::UsersManage);
    }

    public function view(User $user, User $model): bool
    {
        return $this->viewAny($user) && $user->canAccessOrganization($model->organization_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::UsersManage);
    }

    /**
     * A manager can only touch accounts in their scope whose role grants
     * nothing beyond their own permissions.
     */
    public function update(User $user, User $model): bool
    {
        return $user->hasPermission(Permission::UsersManage)
            && $user->canAccessOrganization($model->organization_id)
            && ($model->role === null || $user->holdsAllPermissionsOf($model->role));
    }

    public function delete(User $user, User $model): bool
    {
        return $this->update($user, $model);
    }
}
