<?php

namespace Tests\Feature\Hr;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Driver;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Position;
use App\Models\User;
use App\Models\Waybill;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
    }

    private function actingAsHr(): User
    {
        $user = User::factory()->withRole('hr')->create(['organization_id' => $this->organization->id]);
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        $department = Department::factory()->create(['organization_id' => $this->organization->id]);
        $position = Position::factory()->create();

        return array_replace_recursive([
            'organization_id' => $this->organization->id,
            'last_name' => 'Karimov',
            'first_name' => 'Anvar',
            'middle_name' => 'Olimovich',
            'pinfl' => '12345678901234',
            'passport' => 'AA1234567',
            'phone' => '+998901234567',
            'hire' => [
                'effective_date' => now()->subMonth()->toDateString(),
                'order_number' => '15-K',
                'order_date' => now()->subMonth()->toDateString(),
                'department_id' => $department->id,
                'position_id' => $position->id,
            ],
        ], $overrides);
    }

    public function test_hr_user_creates_employee_with_hire_event(): void
    {
        $this->actingAsHr();
        $payload = $this->payload();

        $response = $this->postJson('/api/employees', $payload)
            ->assertCreated()
            ->assertJsonPath('full_name', 'Karimov Anvar Olimovich')
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('position.id', $payload['hire']['position_id'])
            ->assertJsonPath('department.id', $payload['hire']['department_id']);

        $employee = Employee::findOrFail($response->json('id'));
        $this->assertSame($payload['hire']['effective_date'], $employee->hire_date->toDateString());
        $this->assertSame(21, $employee->annual_leave_days);
        $this->assertSame(['hire'], $employee->events()->pluck('type')->all());
    }

    public function test_pinfl_must_be_14_digits(): void
    {
        $this->actingAsHr();

        $this->postJson('/api/employees', $this->payload(['pinfl' => '1234567890123A']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pinfl');
    }

    public function test_hire_date_cannot_be_in_the_future(): void
    {
        $this->actingAsHr();

        $this->postJson('/api/employees', $this->payload(['hire' => ['effective_date' => now()->addDay()->toDateString()]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('hire.effective_date');
    }

    public function test_cannot_create_employee_in_other_organization(): void
    {
        $this->actingAsHr();
        $other = Organization::factory()->create();

        $this->postJson('/api/employees', $this->payload(['organization_id' => $other->id]))->assertForbidden();
    }

    public function test_list_masks_pinfl_and_passport(): void
    {
        $this->actingAsHr();
        Employee::factory()->create([
            'organization_id' => $this->organization->id, 'pinfl' => '12345678901890', 'passport' => 'AA1234567',
        ]);

        $this->getJson('/api/employees')
            ->assertOk()
            ->assertJsonPath('data.0.pinfl', '123********890')
            ->assertJsonPath('data.0.passport', 'AA*****67')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_list_filters_by_status_and_search(): void
    {
        $this->actingAsHr();
        Employee::factory()->create(['organization_id' => $this->organization->id, 'last_name' => 'Karimov']);
        Employee::factory()->create(['organization_id' => $this->organization->id, 'last_name' => 'Yusupov', 'status' => 'dismissed']);

        $this->getJson('/api/employees?search=Kari')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/employees?status=dismissed')->assertOk()->assertJsonPath('data.0.last_name', 'Yusupov');
    }

    public function test_without_driver_filter_excludes_employees_with_driver_profile(): void
    {
        $this->actingAsHr();
        $withDriver = Employee::factory()->create(['organization_id' => $this->organization->id]);
        Driver::factory()->create(['organization_id' => $this->organization->id, 'employee_id' => $withDriver->id]);
        $free = Employee::factory()->create(['organization_id' => $this->organization->id]);

        $this->getJson('/api/employees?without_driver=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $free->id);
    }

    public function test_card_shows_full_pinfl_and_logs_view(): void
    {
        $this->actingAsHr();
        $employee = Employee::factory()->create(['organization_id' => $this->organization->id, 'pinfl' => '12345678901890']);

        $this->getJson("/api/employees/{$employee->id}")
            ->assertOk()
            ->assertJsonPath('pinfl', '12345678901890');

        $this->assertTrue(AuditLog::where('event', 'viewed')->where('employee_id', $employee->id)->exists());
    }

    public function test_employee_of_other_organization_is_off_limits(): void
    {
        $this->actingAsHr();
        $employee = Employee::factory()->create();

        $this->getJson("/api/employees/{$employee->id}")->assertForbidden();
    }

    public function test_update_cannot_change_department_or_position(): void
    {
        $this->actingAsHr();
        $employee = Employee::factory()->create(['organization_id' => $this->organization->id]);
        $department = Department::factory()->create(['organization_id' => $this->organization->id]);

        $this->putJson("/api/employees/{$employee->id}", ['department_id' => $department->id, 'phone' => '+998900000000'])
            ->assertOk()
            ->assertJsonPath('phone', '+998900000000');

        $this->assertNull($employee->fresh()->department_id);
    }

    public function test_updating_name_syncs_driver_profile(): void
    {
        $this->actingAsHr();
        $employee = Employee::factory()->create(['organization_id' => $this->organization->id]);
        $driver = Driver::factory()->create(['organization_id' => $this->organization->id, 'employee_id' => $employee->id]);

        $this->putJson("/api/employees/{$employee->id}", ['last_name' => 'Rahimov', 'first_name' => 'Bobur', 'middle_name' => null])
            ->assertOk();

        $this->assertSame('Rahimov Bobur', $driver->fresh()->full_name);
    }

    public function test_user_without_organization_sees_empty_list(): void
    {
        Sanctum::actingAs(User::factory()->withRole('hr')->create(['organization_id' => null]));
        Employee::factory()->create();

        $this->getJson('/api/employees')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_viewer_cannot_list_employees(): void
    {
        Sanctum::actingAs(User::factory()->withRole('viewer')->create(['organization_id' => $this->organization->id]));

        $this->getJson('/api/employees')->assertForbidden();
    }

    public function test_department_and_position_with_employees_cannot_be_deleted(): void
    {
        $this->actingAsHr();
        $department = Department::factory()->create(['organization_id' => $this->organization->id]);
        $position = Position::factory()->create();
        Employee::factory()->create([
            'organization_id' => $this->organization->id, 'department_id' => $department->id, 'position_id' => $position->id,
        ]);

        $this->deleteJson("/api/departments/{$department->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', "Bo'limda xodimlar bor");
        $this->deleteJson("/api/positions/{$position->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', "Ma'lumot ishlatilmoqda, o'chirib bo'lmaydi");
    }

    public function test_employee_whose_driver_has_waybills_cannot_be_deleted(): void
    {
        $this->actingAsHr();
        $employee = Employee::factory()->create(['organization_id' => $this->organization->id]);
        $driver = Driver::factory()->create(['organization_id' => $this->organization->id, 'employee_id' => $employee->id]);
        Waybill::factory()->create(['organization_id' => $this->organization->id, 'driver_id' => $driver->id]);

        $this->deleteJson("/api/employees/{$employee->id}")->assertUnprocessable();
        $this->assertNotSoftDeleted($employee);

        $other = Employee::factory()->create(['organization_id' => $this->organization->id]);
        $this->deleteJson("/api/employees/{$other->id}")->assertNoContent();
        $this->assertSoftDeleted($other);
    }

    public function test_history_requires_audit_view(): void
    {
        $this->actingAsHr();
        $employee = Employee::factory()->create(['organization_id' => $this->organization->id]);

        $this->getJson("/api/employees/{$employee->id}/history")->assertForbidden();
    }
}
