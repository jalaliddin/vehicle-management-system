<?php

namespace Tests\Feature\Rbac;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_super_admin_creates_a_role_with_permissions(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $response = $this->postJson('/api/roles', [
            'name' => 'Yoqilgi operatori',
            'description' => 'Shahobcha xodimi',
            'permissions' => ['fuel.view', 'fuel.transactions.create'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('slug', 'yoqilgi_operatori')
            ->assertJsonPath('is_system', false)
            ->assertJsonPath('users_count', 0)
            ->assertJsonPath('permissions', ['fuel.view', 'fuel.transactions.create']);

        $role = Role::firstWhere('slug', 'yoqilgi_operatori');
        $this->assertEqualsCanonicalizing(['fuel.view', 'fuel.transactions.create'], $role->permissionKeys());
    }

    public function test_slugs_stay_unique_when_names_collide_after_slugging(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson('/api/roles', ['name' => 'Kuzatuvchi'])->assertCreated()->assertJsonPath('slug', 'kuzatuvchi');
        $this->postJson('/api/roles', ['name' => 'Kuzatuvchi!'])->assertCreated()->assertJsonPath('slug', 'kuzatuvchi_2');
    }

    public function test_unknown_permissions_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson('/api/roles', ['name' => 'Test', 'permissions' => ['vehicles.fly']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('permissions.0');
    }

    public function test_super_admin_updates_a_system_role_permissions(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $role = Role::firstWhere('slug', 'doctor');

        $this->putJson("/api/roles/{$role->id}", ['permissions' => ['waybills.view']])
            ->assertOk()
            ->assertJsonPath('permissions', ['waybills.view']);

        $this->assertSame(['waybills.view'], $role->fresh()->permissionKeys());
    }

    public function test_super_admin_role_cannot_be_changed(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $role = Role::firstWhere('slug', Role::SUPER_ADMIN);

        $this->putJson("/api/roles/{$role->id}", ['permissions' => []])->assertUnprocessable();
        $this->assertTrue($role->fresh()->isSuperAdmin());
    }

    public function test_system_roles_cannot_be_deleted(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $role = Role::firstWhere('slug', 'viewer');

        $this->deleteJson("/api/roles/{$role->id}")->assertUnprocessable();
        $this->assertModelExists($role);
    }

    public function test_roles_in_use_cannot_be_deleted(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $role = Role::factory()->create();
        User::factory()->create(['role_id' => $role->id]);

        $this->deleteJson("/api/roles/{$role->id}")->assertUnprocessable();
        $this->assertModelExists($role);
    }

    public function test_unused_custom_roles_can_be_deleted(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $role = Role::factory()->withPermissions(Permission::VehiclesView)->create();

        $this->deleteJson("/api/roles/{$role->id}")->assertNoContent();
        $this->assertModelMissing($role);
        $this->assertDatabaseMissing('role_permission', ['role_id' => $role->id]);
    }

    public function test_user_managers_can_list_roles_but_not_change_them(): void
    {
        Sanctum::actingAs(User::factory()->withPermissions(Permission::UsersManage)->create());
        $role = Role::firstWhere('slug', 'viewer');

        $this->getJson('/api/roles')->assertOk()->assertJsonCount(7);
        $this->getJson('/api/permissions')->assertOk()->assertJsonPath('0.key', 'data');
        $this->postJson('/api/roles', ['name' => 'Yangi'])->assertForbidden();
        $this->putJson("/api/roles/{$role->id}", ['name' => 'Boshqa'])->assertForbidden();
        $this->deleteJson("/api/roles/{$role->id}")->assertForbidden();
    }

    public function test_other_users_cannot_see_roles(): void
    {
        Sanctum::actingAs(User::factory()->withRole('hq_dispatcher')->create());

        $this->getJson('/api/roles')->assertForbidden();
        $this->getJson('/api/permissions')->assertForbidden();
    }
}
