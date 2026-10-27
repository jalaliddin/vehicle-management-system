<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => $user->hasPermission($permission));
        }

        Gate::define('view-hr-alerts', fn (User $user) => $user->hasAnyPermission(Permission::OhsView, Permission::EmployeesView));

        Gate::define('access-organization', fn (User $user, ?int $organizationId) => $user->canAccessOrganization($organizationId));
    }
}
