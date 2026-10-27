<?php

namespace Tests\Feature\Hr;

use App\Models\Driver;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverEmployeeLinkTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        Sanctum::actingAs(User::factory()->withRole('dispatcher')->create(['organization_id' => $this->organization->id]));
    }

    /**
     * @return array<string, mixed>
     */
    private function driverPayload(Employee $employee): array
    {
        return [
            'organization_id' => $this->organization->id,
            'employee_id' => $employee->id,
            'license_number' => 'AB1234567',
            'license_category' => 'B,C',
        ];
    }

    public function test_new_driver_takes_identity_from_employee(): void
    {
        $employee = Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'last_name' => 'Karimov', 'first_name' => 'Anvar', 'middle_name' => null,
            'pinfl' => '12345678901234', 'phone' => '+998901112233',
        ]);

        $this->postJson('/api/drivers', $this->driverPayload($employee) + ['full_name' => 'Boshqa Odam'])
            ->assertCreated()
            ->assertJsonPath('full_name', 'Karimov Anvar')
            ->assertJsonPath('pinfl', '12345678901234')
            ->assertJsonPath('phone', '+998901112233')
            ->assertJsonPath('employee_id', $employee->id);
    }

    public function test_new_driver_requires_active_employee_of_same_org(): void
    {
        $foreign = Employee::factory()->create();
        $dismissed = Employee::factory()->create(['organization_id' => $this->organization->id, 'status' => 'dismissed']);

        $this->postJson('/api/drivers', array_diff_key($this->driverPayload($foreign), ['employee_id' => true]))
            ->assertUnprocessable()->assertJsonValidationErrors('employee_id');
        $this->postJson('/api/drivers', $this->driverPayload($foreign))
            ->assertUnprocessable()->assertJsonValidationErrors('employee_id');
        $this->postJson('/api/drivers', $this->driverPayload($dismissed))
            ->assertUnprocessable()->assertJsonValidationErrors('employee_id');
    }

    public function test_employee_can_have_only_one_driver_profile(): void
    {
        $employee = Employee::factory()->create(['organization_id' => $this->organization->id]);
        Driver::factory()->create(['organization_id' => $this->organization->id, 'employee_id' => $employee->id]);

        $this->postJson('/api/drivers', $this->driverPayload($employee))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');
    }

    public function test_linked_driver_identity_cannot_be_edited_directly(): void
    {
        $employee = Employee::factory()->create(['organization_id' => $this->organization->id, 'pinfl' => '12345678901234']);
        $driver = Driver::factory()->create([
            'organization_id' => $this->organization->id, 'employee_id' => $employee->id, 'pinfl' => '12345678901234',
        ]);

        $this->putJson("/api/drivers/{$driver->id}", ['pinfl' => '99999999999999', 'license_category' => 'C'])
            ->assertOk()
            ->assertJsonPath('license_category', 'C')
            ->assertJsonPath('pinfl', '12345678901234');
    }

    public function test_linked_driver_cannot_get_a_vehicle_of_another_organization(): void
    {
        $employee = Employee::factory()->create(['organization_id' => $this->organization->id]);
        $driver = Driver::factory()->create(['organization_id' => $this->organization->id, 'employee_id' => $employee->id]);
        $other = Organization::factory()->create();
        $foreignVehicle = Vehicle::factory()->create(['organization_id' => $other->id]);

        $this->putJson("/api/drivers/{$driver->id}", ['organization_id' => $other->id, 'vehicle_id' => $foreignVehicle->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle_id');

        $this->assertNull($driver->fresh()->vehicle_id);
    }
}
