<?php

namespace Tests\Unit\Hr;

use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Services\Hr\LeaveBalance;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LeaveBalanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_balance_clips_leave_crossing_new_year(): void
    {
        $employee = Employee::factory()->create(['annual_leave_days' => 24]);
        EmployeeAbsence::factory()->for($employee)->create([
            'type' => 'annual_leave', 'start_date' => '2026-12-25', 'end_date' => '2027-01-10',
        ]);

        $this->assertSame(['entitlement' => 24, 'used' => 7, 'remaining' => 17], LeaveBalance::forYear($employee, 2026));
        $this->assertSame(['entitlement' => 24, 'used' => 10, 'remaining' => 14], LeaveBalance::forYear($employee, 2027));
    }

    public function test_planned_and_other_absences_do_not_reduce_balance(): void
    {
        $employee = Employee::factory()->create(['annual_leave_days' => 21]);
        EmployeeAbsence::factory()->for($employee)->create([
            'type' => 'annual_leave', 'start_date' => '2026-06-01', 'end_date' => '2026-06-14', 'is_planned' => true,
        ]);
        EmployeeAbsence::factory()->for($employee)->create([
            'type' => 'sick_leave', 'start_date' => '2026-03-01', 'end_date' => '2026-03-05',
        ]);

        $this->assertSame(['entitlement' => 21, 'used' => 0, 'remaining' => 21], LeaveBalance::forYear($employee, 2026));
    }
}
