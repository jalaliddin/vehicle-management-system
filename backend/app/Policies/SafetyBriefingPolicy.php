<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\SafetyBriefing;
use App\Models\User;

class SafetyBriefingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::OhsView);
    }

    public function view(User $user, SafetyBriefing $briefing): bool
    {
        return $this->viewAny($user) && $user->canAccessOrganization($briefing->organization_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::OhsBriefingsManage);
    }

    public function update(User $user, SafetyBriefing $briefing): bool
    {
        return $user->hasPermission(Permission::OhsBriefingsManage) && $user->canAccessOrganization($briefing->organization_id);
    }

    public function delete(User $user, SafetyBriefing $briefing): bool
    {
        return $this->update($user, $briefing);
    }
}
