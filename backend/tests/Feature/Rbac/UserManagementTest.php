<?php

namespace Tests\Feature\Rbac;

use App\Enums\Permission;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Organization $organization;

    private Organization $otherOrganization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->otherOrganization = Organization::factory()->create();
    }

    public function test_users_without_permission_cannot_list_users(): void
    {
        Sanctum::actingAs(User::factory()->withRole('hq_dispatcher')->create());

        $this->getJson('/api/users')->assertForbidden();
    }

    public function test_scoped_managers_only_see_their_organization(): void
    {
        Sanctum::actingAs($this->manager());
        User::factory()->withRole('mechanic')->create(['organization_id' => $this->organization->id]);
        User::factory()->withRole('mechanic')->create(['organization_id' => $this->otherOrganization->id]);

        $response = $this->getJson('/api/users')->assertOk();

        $this->assertCount(2, $response->json());
        $this->assertSame([$this->organization->id], array_values(array_unique(array_column($response->json(), 'organization_id'))));
    }

    public function test_manager_creates_user_with_a_role_they_fully_hold(): void
    {
        Sanctum::actingAs($this->manager(Permission::VehiclesView, Permission::DriversView, Permission::WaybillsView, Permission::WaybillsMechanicCheck));
        $mechanic = Role::firstWhere('slug', 'mechanic');

        $this->postJson('/api/users', $this->userPayload(['role_id' => $mechanic->id]))
            ->assertCreated()
            ->assertJsonPath('role', 'mechanic')
            ->assertJsonPath('role_name', 'Mexanik');
    }

    public function test_manager_cannot_grant_a_role_with_more_permissions_than_their_own(): void
    {
        Sanctum::actingAs($this->manager());
        $hq = Role::firstWhere('slug', 'hq_dispatcher');

        $this->postJson('/api/users', $this->userPayload(['role_id' => $hq->id]))->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'new@utg.uz']);
    }

    public function test_only_super_admin_can_grant_the_super_admin_role(): void
    {
        $superAdminRole = Role::firstWhere('slug', Role::SUPER_ADMIN);

        Sanctum::actingAs(User::factory()->withPermissions(...Permission::cases())->create());
        $this->postJson('/api/users', $this->userPayload(['role_id' => $superAdminRole->id, 'organization_id' => null]))
            ->assertForbidden();

        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $this->postJson('/api/users', $this->userPayload(['role_id' => $superAdminRole->id, 'organization_id' => null]))
            ->assertCreated();
    }

    public function test_scoped_manager_cannot_create_users_in_other_organizations(): void
    {
        Sanctum::actingAs($this->manager());
        $role = Role::factory()->create();

        $this->postJson('/api/users', $this->userPayload([
            'role_id' => $role->id,
            'organization_id' => $this->otherOrganization->id,
        ]))->assertForbidden();

        $this->postJson('/api/users', $this->userPayload(['role_id' => $role->id, 'organization_id' => null]))
            ->assertForbidden();
    }

    public function test_manager_cannot_edit_accounts_with_more_rights(): void
    {
        Sanctum::actingAs($this->manager());
        $hqUser = User::factory()->withRole('hq_dispatcher')->create(['organization_id' => $this->organization->id]);

        $this->putJson("/api/users/{$hqUser->id}", ['password' => 'hijacked'])->assertForbidden();
        $this->postJson("/api/users/{$hqUser->id}/toggle-active")->assertForbidden();
        $this->deleteJson("/api/users/{$hqUser->id}")->assertForbidden();
    }

    public function test_manager_cannot_edit_users_of_other_organizations(): void
    {
        Sanctum::actingAs($this->manager());
        $other = User::factory()->create(['organization_id' => $this->otherOrganization->id]);

        $this->getJson("/api/users/{$other->id}")->assertForbidden();
        $this->putJson("/api/users/{$other->id}", ['name' => 'X'])->assertForbidden();
    }

    public function test_users_cannot_change_their_own_role(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);
        $viewer = Role::firstWhere('slug', 'viewer');

        $this->putJson("/api/users/{$admin->id}", ['role_id' => $viewer->id])->assertUnprocessable();
        $this->assertTrue($admin->fresh()->isSuperAdmin());

        $this->putJson("/api/users/{$admin->id}", ['role_id' => $admin->role_id, 'name' => 'Admin'])->assertOk();
    }

    public function test_super_admin_changes_a_users_role(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $user = User::factory()->withRole('viewer')->create();
        $doctor = Role::firstWhere('slug', 'doctor');

        $this->putJson("/api/users/{$user->id}", ['role_id' => $doctor->id])
            ->assertOk()
            ->assertJsonPath('role', 'doctor');
    }

    private function manager(Permission ...$extra): User
    {
        return User::factory()
            ->withPermissions(Permission::UsersView, Permission::UsersManage, ...$extra)
            ->create(['organization_id' => $this->organization->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function userPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Yangi Xodim',
            'email' => 'new@utg.uz',
            'password' => 'secret123',
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ], $overrides);
    }
}
