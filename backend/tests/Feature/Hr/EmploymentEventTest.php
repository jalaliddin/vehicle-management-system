<?php

namespace Tests\Feature\Hr;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Driver;
use App\Models\Employee;
use App\Models\EmploymentEvent;
use App\Models\Organization;
use App\Models\Position;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Hr\DriverEmployeeBackfill;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmploymentEventTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Organization $organization;

    private Department $department;

    private Position $position;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->department = Department::factory()->create(['organization_id' => $this->organization->id]);
        $this->position = Position::factory()->create();
    }

    private function actingAsHr(?Organization $organization = null, bool $allOrganizations = false): User
    {
        $user = User::factory()->withRole($allOrganizations ? 'superadmin' : 'hr')
            ->create(['organization_id' => ($organization ?? $this->organization)->id]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function hiredEmployee(string $hireDate = '-1 year'): Employee
    {
        $response = $this->postJson('/api/employees', [
            'organization_id' => $this->organization->id,
            'last_name' => 'Karimov',
            'first_name' => 'Anvar',
            'pinfl' => fake()->unique()->numerify('##############'),
            'hire' => [
                'effective_date' => now()->modify($hireDate)->toDateString(),
                'order_number' => '1-K',
                'order_date' => now()->modify($hireDate)->toDateString(),
                'department_id' => $this->department->id,
                'position_id' => $this->position->id,
            ],
        ])->assertCreated();

        return Employee::findOrFail($response->json('id'));
    }

    /**
     * @return array<string, mixed>
     */
    private function order(string $type, string $date = '-1 month', array $extra = []): array
    {
        return array_merge([
            'type' => $type,
            'effective_date' => now()->modify($date)->toDateString(),
            'order_number' => '2-K',
            'order_date' => now()->modify($date)->toDateString(),
        ], $extra);
    }

    public function test_transfer_updates_current_assignment(): void
    {
        $this->actingAsHr();
        $employee = $this->hiredEmployee();
        $newDepartment = Department::factory()->create(['organization_id' => $this->organization->id]);

        $this->postJson("/api/employees/{$employee->id}/events", $this->order('transfer', extra: ['department_id' => $newDepartment->id]))
            ->assertCreated();

        $employee->refresh();
        $this->assertSame($newDepartment->id, $employee->department_id);
        $this->assertSame($this->position->id, $employee->position_id);
        $this->assertSame(['hire', 'transfer'], $employee->events()->orderBy('id')->pluck('type')->all());
    }

    public function test_transfer_must_change_something(): void
    {
        $this->actingAsHr();
        $employee = $this->hiredEmployee();

        $this->postJson("/api/employees/{$employee->id}/events", $this->order('transfer', extra: ['department_id' => $this->department->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_transfer_dated_before_latest_event_is_rejected(): void
    {
        $this->actingAsHr();
        $employee = $this->hiredEmployee('-1 month');
        $newDepartment = Department::factory()->create(['organization_id' => $this->organization->id]);

        $this->postJson("/api/employees/{$employee->id}/events", $this->order('transfer', '-2 months', ['department_id' => $newDepartment->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('effective_date');

        $this->assertSame($this->department->id, $employee->fresh()->department_id);
    }

    public function test_future_dated_event_is_rejected(): void
    {
        $this->actingAsHr();
        $employee = $this->hiredEmployee();

        $this->postJson("/api/employees/{$employee->id}/events", $this->order('dismissal', '+1 day', ['dismissal_reason' => 'own_wish']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('effective_date');
    }

    public function test_dismissal_requires_a_known_reason(): void
    {
        $this->actingAsHr();
        $employee = $this->hiredEmployee();

        $this->postJson("/api/employees/{$employee->id}/events", $this->order('dismissal', extra: ['dismissal_reason' => 'bored']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('dismissal_reason');
    }

    public function test_dismissal_sets_status_and_deactivates_driver(): void
    {
        $this->actingAsHr();
        $employee = $this->hiredEmployee();
        $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id]);
        $driver = Driver::factory()->create([
            'organization_id' => $this->organization->id, 'employee_id' => $employee->id, 'vehicle_id' => $vehicle->id,
        ]);

        $this->postJson("/api/employees/{$employee->id}/events", $this->order('dismissal', extra: ['dismissal_reason' => 'own_wish']))
            ->assertCreated();

        $employee->refresh();
        $this->assertSame('dismissed', $employee->status);
        $this->assertSame(now()->subMonth()->toDateString(), $employee->dismissal_date->toDateString());
        $this->assertSame('inactive', $driver->fresh()->status);
        $this->assertNull($driver->fresh()->vehicle_id);
    }

    public function test_dismissed_employee_cannot_be_transferred_or_dismissed_again(): void
    {
        $this->actingAsHr();
        $employee = $this->hiredEmployee();
        $this->postJson("/api/employees/{$employee->id}/events", $this->order('dismissal', extra: ['dismissal_reason' => 'own_wish']))
            ->assertCreated();

        $this->postJson("/api/employees/{$employee->id}/events", $this->order('dismissal', 'today', ['dismissal_reason' => 'own_wish']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_transfer_to_other_org_clears_foreign_vehicle(): void
    {
        $this->actingAsHr(allOrganizations: true);
        $employee = $this->hiredEmployee();
        $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id]);
        $driver = Driver::factory()->create([
            'organization_id' => $this->organization->id, 'employee_id' => $employee->id, 'vehicle_id' => $vehicle->id,
        ]);
        $target = Organization::factory()->create();
        $targetDepartment = Department::factory()->create(['organization_id' => $target->id]);

        $this->postJson("/api/employees/{$employee->id}/events", $this->order('transfer', extra: [
            'organization_id' => $target->id, 'department_id' => $targetDepartment->id,
        ]))->assertCreated();

        $this->assertSame($target->id, $employee->fresh()->organization_id);
        $this->assertSame($target->id, $driver->fresh()->organization_id);
        $this->assertNull($driver->fresh()->vehicle_id);
    }

    public function test_transfer_department_must_belong_to_target_organization(): void
    {
        $this->actingAsHr();
        $employee = $this->hiredEmployee();
        $foreign = Department::factory()->create();

        $this->postJson("/api/employees/{$employee->id}/events", $this->order('transfer', extra: ['department_id' => $foreign->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('department_id');
    }

    public function test_scoped_user_cannot_transfer_to_other_organization(): void
    {
        $this->actingAsHr();
        $employee = $this->hiredEmployee();
        $target = Organization::factory()->create();

        $this->postJson("/api/employees/{$employee->id}/events", $this->order('transfer', extra: ['organization_id' => $target->id]))
            ->assertForbidden();
    }

    public function test_cancel_latest_event_replays_state(): void
    {
        $this->actingAsHr();
        $employee = $this->hiredEmployee();
        $newDepartment = Department::factory()->create(['organization_id' => $this->organization->id]);
        $transferId = $this->postJson("/api/employees/{$employee->id}/events", $this->order('transfer', extra: ['department_id' => $newDepartment->id]))
            ->assertCreated()
            ->json('id');

        $this->postJson("/api/employment-events/{$transferId}/cancel", ['reason' => 'Xato kiritilgan'])->assertOk();

        $this->assertSame($this->department->id, $employee->fresh()->department_id);
        $this->assertNotNull(EmploymentEvent::find($transferId)->cancelled_at);
        $this->assertTrue(AuditLog::where('event', 'cancelled')->where('auditable_id', $transferId)->exists());
    }

    public function test_only_latest_event_can_be_cancelled(): void
    {
        $this->actingAsHr();
        $employee = $this->hiredEmployee();
        $hireId = $employee->events()->value('id');
        $newDepartment = Department::factory()->create(['organization_id' => $this->organization->id]);
        $this->postJson("/api/employees/{$employee->id}/events", $this->order('transfer', extra: ['department_id' => $newDepartment->id]))
            ->assertCreated();

        $this->postJson("/api/employment-events/{$hireId}/cancel", ['reason' => 'Xato'])->assertUnprocessable();
    }

    public function test_sole_hire_cannot_be_cancelled(): void
    {
        $this->actingAsHr();
        $employee = $this->hiredEmployee();

        $this->postJson("/api/employment-events/{$employee->events()->value('id')}/cancel", ['reason' => 'Xato'])
            ->assertUnprocessable();
    }

    public function test_rehire_after_dismissal(): void
    {
        $this->actingAsHr();
        $employee = $this->hiredEmployee();
        $this->postJson("/api/employees/{$employee->id}/events", $this->order('dismissal', '-2 months', ['dismissal_reason' => 'own_wish']))
            ->assertCreated();

        $this->postJson("/api/employees/{$employee->id}/events", $this->order('hire', '-1 month', ['position_id' => $this->position->id]))
            ->assertCreated();

        $employee->refresh();
        $this->assertSame('active', $employee->status);
        $this->assertNull($employee->dismissal_date);
        $this->assertSame(now()->subMonth()->toDateString(), $employee->hire_date->toDateString());
    }

    public function test_active_employee_cannot_be_hired_again(): void
    {
        $this->actingAsHr();
        $employee = $this->hiredEmployee();

        $this->postJson("/api/employees/{$employee->id}/events", $this->order('hire', extra: ['position_id' => $this->position->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_backfilled_driver_can_be_transferred_dismissed_and_restored(): void
    {
        $this->actingAsHr();
        $driver = Driver::factory()->create(['organization_id' => $this->organization->id, 'employee_id' => null]);
        $driver->forceFill(['created_at' => now()->subYear()])->save();
        DriverEmployeeBackfill::run();
        $employee = $driver->fresh()->employee;

        $this->postJson("/api/employees/{$employee->id}/events", $this->order('transfer', '-2 months', ['department_id' => $this->department->id]))
            ->assertCreated();
        $dismissalId = $this->postJson("/api/employees/{$employee->id}/events", $this->order('dismissal', '-1 month', ['dismissal_reason' => 'own_wish']))
            ->assertCreated()->json('id');

        $this->postJson("/api/employment-events/{$dismissalId}/cancel", ['reason' => 'Xato'])->assertOk();

        $employee->refresh();
        $this->assertSame('active', $employee->status);
        $this->assertNull($employee->dismissal_date);
        $this->assertSame($this->department->id, $employee->department_id);
    }

    public function test_events_are_listed_newest_first(): void
    {
        $this->actingAsHr();
        $employee = $this->hiredEmployee();
        $newDepartment = Department::factory()->create(['organization_id' => $this->organization->id]);
        $this->postJson("/api/employees/{$employee->id}/events", $this->order('transfer', extra: ['department_id' => $newDepartment->id]));

        $this->getJson("/api/employees/{$employee->id}/events")
            ->assertOk()
            ->assertJsonPath('0.type', 'transfer')
            ->assertJsonPath('1.type', 'hire');
    }
}
