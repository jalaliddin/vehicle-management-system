<?php

namespace Tests\Feature\Rbac;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthPermissionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_returns_role_and_permissions(): void
    {
        $user = User::factory()->withRole('mechanic')->create(['email' => 'm@utg.uz']);

        $this->postJson('/api/auth/login', ['email' => 'm@utg.uz', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.role', 'mechanic')
            ->assertJsonPath('user.role_id', $user->role_id)
            ->assertJsonPath('user.role_name', 'Mexanik')
            ->assertJsonPath('user.permissions', [
                'vehicles.view', 'drivers.view', 'waybills.view', 'waybills.mechanic_check',
            ]);
    }

    public function test_super_admin_receives_every_permission(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('role', 'superadmin')
            ->assertJsonPath('permissions', Permission::values());
    }

    public function test_user_without_role_has_no_permissions(): void
    {
        Sanctum::actingAs(User::factory()->create(['role_id' => null]));

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('role', null)
            ->assertJsonPath('permissions', []);

        $this->getJson('/api/vehicles')
            ->assertForbidden()
            ->assertJsonPath('message', "Ruxsat yo'q.");
    }

    public function test_system_roles_are_seeded_by_the_migration(): void
    {
        $this->assertDatabaseCount('roles', 6);

        foreach (['superadmin', 'hq_dispatcher', 'dispatcher', 'mechanic', 'doctor', 'viewer'] as $slug) {
            $this->assertDatabaseHas('roles', ['slug' => $slug, 'is_system' => true]);
        }
    }
}
