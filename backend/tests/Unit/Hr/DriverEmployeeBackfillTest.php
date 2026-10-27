<?php

namespace Tests\Unit\Hr;

use App\Models\Driver;
use App\Models\Employee;
use App\Models\Position;
use App\Support\Hr\DriverEmployeeBackfill;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DriverEmployeeBackfillTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_employee_per_driver_and_links_it(): void
    {
        $driver = Driver::factory()->create([
            'employee_id' => null, 'full_name' => 'Karimov Anvar', 'pinfl' => '12345678901234',
            'phone' => '+998901112233', 'employment_type' => 'contract',
        ]);

        $this->assertSame(1, DriverEmployeeBackfill::run());

        $employee = $driver->fresh()->employee;
        $this->assertNotNull($employee);
        $this->assertSame($driver->organization_id, $employee->organization_id);
        $this->assertSame('12345678901234', $employee->pinfl);
        $this->assertSame('+998901112233', $employee->phone);
        $this->assertSame('contract', $employee->employment_type);
        $this->assertSame('active', $employee->status);
        $this->assertSame('Haydovchi', $employee->position->name);
        $this->assertSame(1, Position::where('name', 'Haydovchi')->count());
    }

    public function test_writes_a_baseline_hire_event_dated_when_the_driver_was_registered(): void
    {
        $driver = Driver::factory()->create(['employee_id' => null]);
        $driver->forceFill(['created_at' => '2025-03-01 10:00:00'])->save();

        DriverEmployeeBackfill::run();

        $employee = $driver->fresh()->employee;
        $this->assertSame(['hire'], $employee->events()->pluck('type')->all());
        $this->assertSame('2025-03-01', $employee->events()->first()->effective_date->toDateString());
        $this->assertSame($driver->organization_id, $employee->events()->first()->organization_id);
        $this->assertSame('2025-03-01', $employee->hire_date->toDateString());
    }

    public function test_splits_messy_names(): void
    {
        $three = Driver::factory()->create(['employee_id' => null, 'full_name' => '  Karimov   Anvar  Olimovich ']);
        $one = Driver::factory()->create(['employee_id' => null, 'full_name' => 'Karimov']);

        DriverEmployeeBackfill::run();

        $this->assertSame(['Karimov', 'Anvar', 'Olimovich'], [
            $three->fresh()->employee->last_name, $three->fresh()->employee->first_name, $three->fresh()->employee->middle_name,
        ]);
        $this->assertSame(['Karimov', '', null], [
            $one->fresh()->employee->last_name, $one->fresh()->employee->first_name, $one->fresh()->employee->middle_name,
        ]);
    }

    public function test_links_existing_employee_with_same_pinfl(): void
    {
        $employee = Employee::factory()->create(['pinfl' => '12345678901234']);
        $driver = Driver::factory()->create([
            'employee_id' => null, 'organization_id' => $employee->organization_id, 'pinfl' => '12345678901234',
        ]);

        DriverEmployeeBackfill::run();

        $this->assertSame($employee->id, $driver->fresh()->employee_id);
        $this->assertSame(1, Employee::count());
    }

    public function test_is_idempotent(): void
    {
        Driver::factory()->create(['employee_id' => null]);

        DriverEmployeeBackfill::run();

        $this->assertSame(0, DriverEmployeeBackfill::run());
    }
}
