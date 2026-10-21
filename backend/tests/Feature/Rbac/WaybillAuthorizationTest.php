<?php

namespace Tests\Feature\Rbac;

use App\Models\Driver;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Waybill;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WaybillAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
    }

    public function test_the_full_workflow_with_system_roles(): void
    {
        $waybill = $this->createWaybillAs('dispatcher');

        $this->actAs('dispatcher')->postJson("/api/waybills/{$waybill->id}/submit")->assertOk()->assertJsonPath('status', 'mechanic_check');
        $this->actAs('mechanic')->postJson("/api/waybills/{$waybill->id}/mechanic-check", ['passed' => true])->assertOk()->assertJsonPath('status', 'mechanic_ok');
        $this->actAs('doctor')->postJson("/api/waybills/{$waybill->id}/doctor-check", ['passed' => true])->assertOk()->assertJsonPath('status', 'doctor_ok');
        $this->actAs('hq_dispatcher')->postJson("/api/waybills/{$waybill->id}/approve")->assertOk()->assertJsonPath('status', 'approved');
        $this->actAs('dispatcher')->postJson("/api/waybills/{$waybill->id}/depart")->assertOk()->assertJsonPath('status', 'in_progress');
        $this->actAs('dispatcher')->postJson("/api/waybills/{$waybill->id}/complete", ['actual_km' => 42])->assertOk()->assertJsonPath('status', 'completed');
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function forbiddenSteps(): array
    {
        return [
            'viewer approves' => ['viewer', 'doctor_ok', 'approve'],
            'dispatcher approves' => ['dispatcher', 'doctor_ok', 'approve'],
            'mechanic does doctor check' => ['mechanic', 'mechanic_ok', 'doctor-check'],
            'doctor does mechanic check' => ['doctor', 'mechanic_check', 'mechanic-check'],
            'mechanic departs' => ['mechanic', 'approved', 'depart'],
            'hq does mechanic check' => ['hq_dispatcher', 'mechanic_check', 'mechanic-check'],
        ];
    }

    #[DataProvider('forbiddenSteps')]
    public function test_roles_cannot_take_steps_they_lack(string $role, string $status, string $action): void
    {
        $waybill = Waybill::factory()->status($status)->create(['organization_id' => $this->organization->id]);

        $this->actAs($role)->postJson("/api/waybills/{$waybill->id}/{$action}", ['passed' => true])
            ->assertForbidden()
            ->assertJsonPath('message', "Ruxsat yo'q.");

        $this->assertSame($status, $waybill->fresh()->status);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function outOfOrderSteps(): array
    {
        return [
            'approve a draft' => ['hq_dispatcher', 'draft', 'approve'],
            'depart before approval' => ['dispatcher', 'doctor_ok', 'depart'],
            'complete before departure' => ['dispatcher', 'approved', 'complete'],
            'submit twice' => ['dispatcher', 'mechanic_check', 'submit'],
            'mechanic check a draft' => ['mechanic', 'draft', 'mechanic-check'],
        ];
    }

    #[DataProvider('outOfOrderSteps')]
    public function test_steps_must_match_the_current_status(string $role, string $status, string $action): void
    {
        $waybill = Waybill::factory()->status($status)->create(['organization_id' => $this->organization->id]);

        $this->actAs($role)
            ->postJson("/api/waybills/{$waybill->id}/{$action}", ['passed' => true])
            ->assertUnprocessable();

        $this->assertSame($status, $waybill->fresh()->status);
    }

    public function test_only_drafts_can_be_edited_or_deleted(): void
    {
        $waybill = Waybill::factory()->status('approved')->create(['organization_id' => $this->organization->id]);

        $this->actAs('dispatcher')->putJson("/api/waybills/{$waybill->id}", ['destination' => 'X'])->assertUnprocessable();
        $this->actAs('dispatcher')->deleteJson("/api/waybills/{$waybill->id}")->assertUnprocessable();

        $draft = Waybill::factory()->create(['organization_id' => $this->organization->id]);
        $this->actAs('dispatcher')->deleteJson("/api/waybills/{$draft->id}")->assertNoContent();
    }

    public function test_scoped_users_cannot_touch_other_organizations_waybills(): void
    {
        $foreign = Waybill::factory()->status('mechanic_check')->create();

        $this->actAs('mechanic')->getJson("/api/waybills/{$foreign->id}")->assertForbidden();
        $this->actAs('mechanic')->postJson("/api/waybills/{$foreign->id}/mechanic-check", ['passed' => true])->assertForbidden();
        $this->assertSame('mechanic_check', $foreign->fresh()->status);
    }

    public function test_index_is_scoped_to_the_users_organization(): void
    {
        Waybill::factory()->count(2)->create(['organization_id' => $this->organization->id]);
        Waybill::factory()->create();

        $this->actAs('viewer')->getJson('/api/waybills')->assertOk()->assertJsonPath('total', 2);
        $this->actAs('hq_dispatcher')->getJson('/api/waybills')->assertOk()->assertJsonPath('total', 3);
    }

    public function test_dispatchers_cannot_create_waybills_for_other_organizations(): void
    {
        $other = Organization::factory()->create();
        $vehicle = Vehicle::factory()->create(['organization_id' => $other->id]);
        $driver = Driver::factory()->create(['organization_id' => $other->id]);

        $this->actAs('dispatcher')
            ->postJson('/api/waybills', $this->waybillPayload($other->id, $vehicle->id, $driver->id))
            ->assertForbidden();
    }

    public function test_waybill_vehicle_and_driver_must_belong_to_its_organization(): void
    {
        $foreignVehicle = Vehicle::factory()->create();
        $driver = Driver::factory()->create(['organization_id' => $this->organization->id]);

        $this->actAs('dispatcher')
            ->postJson('/api/waybills', $this->waybillPayload($this->organization->id, $foreignVehicle->id, $driver->id))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle_id');
    }

    private function createWaybillAs(string $role): Waybill
    {
        $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id]);
        $driver = Driver::factory()->create(['organization_id' => $this->organization->id]);

        $id = $this->actAs($role)
            ->postJson('/api/waybills', $this->waybillPayload($this->organization->id, $vehicle->id, $driver->id))
            ->assertCreated()
            ->json('id');

        return Waybill::findOrFail($id);
    }

    private function actAs(string $role): static
    {
        Sanctum::actingAs(User::factory()->withRole($role)->create(['organization_id' => $this->organization->id]));

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    private function waybillPayload(int $organizationId, int $vehicleId, int $driverId): array
    {
        return [
            'organization_id' => $organizationId,
            'vehicle_id' => $vehicleId,
            'driver_id' => $driverId,
            'vehicle_type' => 'car',
            'created_date' => today()->toDateString(),
            'valid_until' => today()->addDay()->toDateString(),
            'destination' => 'Xiva',
        ];
    }
}
