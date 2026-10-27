<?php

namespace Tests\Feature\Rbac;

use App\Enums\Permission;
use App\Models\Driver;
use App\Models\FuelStation;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ResourceAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
    }

    /**
     * @return array<string, array{string, string, Permission}>
     */
    public static function protectedEndpoints(): array
    {
        return [
            'list vehicles' => ['GET', '/api/vehicles', Permission::VehiclesView],
            'create vehicle' => ['POST', '/api/vehicles', Permission::VehiclesCreate],
            'list drivers' => ['GET', '/api/drivers', Permission::DriversView],
            'create driver' => ['POST', '/api/drivers', Permission::DriversCreate],
            'list waybills' => ['GET', '/api/waybills', Permission::WaybillsView],
            'create waybill' => ['POST', '/api/waybills', Permission::WaybillsCreate],
            'fuel monitoring' => ['GET', '/api/fuel/monitoring', Permission::FuelView],
            'list fuel stations' => ['GET', '/api/fuel/stations', Permission::FuelView],
            'create fuel station' => ['POST', '/api/fuel/stations', Permission::FuelStationsManage],
            'fuel transactions' => ['GET', '/api/fuel/transactions', Permission::FuelView],
            'list fuel norms' => ['GET', '/api/fuel-norms', Permission::FuelNormsView],
            'create fuel norm' => ['POST', '/api/fuel-norms', Permission::FuelNormsManage],
            'create organization' => ['POST', '/api/organizations', Permission::OrganizationsManage],
            'waybill report' => ['GET', '/api/reports/waybills', Permission::ReportsView],
            'fuel report' => ['GET', '/api/reports/fuel', Permission::ReportsView],
            'vehicle report' => ['GET', '/api/reports/vehicles', Permission::ReportsView],
            'driver report' => ['GET', '/api/reports/drivers', Permission::ReportsView],
            'fuel consumption report' => ['GET', '/api/reports/fuel-consumption', Permission::ReportsView],
            'list users' => ['GET', '/api/users', Permission::UsersView],
        ];
    }

    #[DataProvider('protectedEndpoints')]
    public function test_endpoint_requires_its_permission(string $method, string $uri, Permission $permission): void
    {
        Sanctum::actingAs(User::factory()->withPermissions()->create(['organization_id' => $this->organization->id]));
        $this->json($method, $uri)->assertForbidden();

        Sanctum::actingAs(User::factory()->withPermissions($permission)->create(['organization_id' => $this->organization->id]));
        $this->assertContains($this->json($method, $uri)->status(), [200, 422]);
    }

    public function test_everyone_can_list_organizations_and_see_the_dashboard(): void
    {
        Sanctum::actingAs(User::factory()->withPermissions()->create(['organization_id' => $this->organization->id]));

        $this->getJson('/api/organizations')->assertOk();
        $this->getJson('/api/dashboard/stats')->assertOk();
    }

    public function test_organization_details_are_limited_to_the_users_own(): void
    {
        $other = Organization::factory()->create();
        Sanctum::actingAs(User::factory()->withRole('viewer')->create(['organization_id' => $this->organization->id]));

        $this->getJson("/api/organizations/{$this->organization->id}")->assertOk();
        $this->getJson("/api/organizations/{$other->id}")->assertForbidden();
    }

    public function test_vehicles_of_other_organizations_are_off_limits(): void
    {
        $foreign = Vehicle::factory()->create();
        Sanctum::actingAs(User::factory()->withRole('hq_dispatcher')->create());
        $this->getJson("/api/vehicles/{$foreign->id}")->assertOk();

        Sanctum::actingAs(User::factory()
            ->withPermissions(Permission::VehiclesView, Permission::VehiclesUpdate, Permission::VehiclesDelete)
            ->create(['organization_id' => $this->organization->id]));

        $this->getJson("/api/vehicles/{$foreign->id}")->assertForbidden();
        $this->putJson("/api/vehicles/{$foreign->id}", ['model' => 'X'])->assertForbidden();
        $this->putJson("/api/vehicles/{$foreign->id}/status", ['status' => 'broken'])->assertForbidden();
        $this->deleteJson("/api/vehicles/{$foreign->id}")->assertForbidden();
        $this->assertModelExists($foreign);
    }

    public function test_scoped_users_cannot_move_records_to_other_organizations(): void
    {
        $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id]);
        $other = Organization::factory()->create();
        Sanctum::actingAs(User::factory()->withRole('dispatcher')->create(['organization_id' => $this->organization->id]));

        $this->putJson("/api/vehicles/{$vehicle->id}", ['organization_id' => $other->id])->assertForbidden();
        $this->assertSame($this->organization->id, $vehicle->fresh()->organization_id);

        $this->putJson("/api/vehicles/{$vehicle->id}", ['model' => 'Cobalt'])->assertOk();
    }

    public function test_drivers_can_only_be_assigned_vehicles_of_their_organization(): void
    {
        $driver = Driver::factory()->create(['organization_id' => $this->organization->id]);
        $ownVehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id]);
        $foreignVehicle = Vehicle::factory()->create();
        Sanctum::actingAs(User::factory()->withRole('dispatcher')->create(['organization_id' => $this->organization->id]));

        $this->postJson("/api/drivers/{$driver->id}/assign", ['vehicle_id' => $foreignVehicle->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle_id');
        $this->postJson("/api/drivers/{$driver->id}/assign", ['vehicle_id' => $ownVehicle->id])->assertOk();
    }

    public function test_dispatcher_cannot_delete_vehicles_or_drivers_by_default(): void
    {
        $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id]);
        $driver = Driver::factory()->create(['organization_id' => $this->organization->id]);
        Sanctum::actingAs(User::factory()->withRole('dispatcher')->create(['organization_id' => $this->organization->id]));

        $this->deleteJson("/api/vehicles/{$vehicle->id}")->assertForbidden();
        $this->deleteJson("/api/drivers/{$driver->id}")->assertForbidden();
    }

    public function test_fuel_transactions_are_limited_to_own_stations(): void
    {
        $ownStation = FuelStation::factory()->create(['organization_id' => $this->organization->id]);
        $foreignStation = FuelStation::factory()->create();
        Sanctum::actingAs(User::factory()->withRole('dispatcher')->create(['organization_id' => $this->organization->id]));

        $this->postJson("/api/fuel/stations/{$foreignStation->id}/shifts", ['opening_measured' => 5000])->assertForbidden();
        $this->postJson("/api/fuel/stations/{$ownStation->id}/shifts", ['opening_measured' => 5000])->assertCreated();
        $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id, 'fuel_type' => $ownStation->fuel_type]);
        $this->postJson("/api/fuel/stations/{$ownStation->id}/refuels", ['vehicle_id' => $vehicle->id, 'odometer' => 100, 'amount' => 10])->assertCreated();
        $this->assertEquals(4990, $ownStation->fresh()->current_balance);
    }

    public function test_dashboard_shows_nothing_to_scoped_users_without_an_organization(): void
    {
        Vehicle::factory()->count(3)->create();
        Sanctum::actingAs(User::factory()->withRole('viewer')->create(['organization_id' => null]));

        $this->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('total_vehicles', 0)
            ->assertJsonPath('vehicles_by_org', []);
    }
}
