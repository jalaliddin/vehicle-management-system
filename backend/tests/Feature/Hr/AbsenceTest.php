<?php

namespace Tests\Feature\Hr;

use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AbsenceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Organization $organization;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->employee = Employee::factory()->create([
            'organization_id' => $this->organization->id, 'hire_date' => now()->subYears(2)->toDateString(),
        ]);
        Sanctum::actingAs(User::factory()->withRole('hr')->create(['organization_id' => $this->organization->id]));
    }

    /**
     * @return array<string, mixed>
     */
    private function absence(string $start, string $end, array $extra = []): array
    {
        return array_merge(['type' => 'annual_leave', 'start_date' => $start, 'end_date' => $end], $extra);
    }

    public function test_days_are_computed_on_save(): void
    {
        $start = now()->subDays(20)->toDateString();
        $end = now()->subDays(7)->toDateString();

        $this->postJson("/api/employees/{$this->employee->id}/absences", $this->absence($start, $end))
            ->assertCreated()
            ->assertJsonPath('days', 14);
    }

    public function test_overlapping_actual_absences_are_rejected(): void
    {
        EmployeeAbsence::factory()->for($this->employee)->create([
            'start_date' => now()->subDays(20)->toDateString(), 'end_date' => now()->subDays(10)->toDateString(),
        ]);

        $this->postJson("/api/employees/{$this->employee->id}/absences", $this->absence(
            now()->subDays(12)->toDateString(), now()->subDays(5)->toDateString(), ['type' => 'sick_leave'],
        ))->assertUnprocessable()->assertJsonValidationErrors('start_date');
    }

    public function test_planned_and_actual_may_overlap(): void
    {
        EmployeeAbsence::factory()->for($this->employee)->create([
            'start_date' => now()->subDays(20)->toDateString(), 'end_date' => now()->subDays(10)->toDateString(), 'is_planned' => true,
        ]);

        $this->postJson("/api/employees/{$this->employee->id}/absences", $this->absence(
            now()->subDays(20)->toDateString(), now()->subDays(10)->toDateString(),
        ))->assertCreated();
    }

    public function test_planned_absence_may_be_in_the_future_actual_may_not(): void
    {
        $start = now()->addMonth()->toDateString();
        $end = now()->addMonth()->addDays(13)->toDateString();

        $this->postJson("/api/employees/{$this->employee->id}/absences", $this->absence($start, $end))
            ->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $this->postJson("/api/employees/{$this->employee->id}/absences", $this->absence($start, $end, ['is_planned' => true]))
            ->assertCreated();
    }

    public function test_absence_before_hire_or_after_dismissal_is_rejected(): void
    {
        $this->postJson("/api/employees/{$this->employee->id}/absences", $this->absence(
            now()->subYears(3)->toDateString(), now()->subYears(3)->addDays(5)->toDateString(),
        ))->assertUnprocessable()->assertJsonValidationErrors('start_date');

        $dismissed = Employee::factory()->create([
            'organization_id' => $this->organization->id, 'status' => 'dismissed',
            'hire_date' => now()->subYear()->toDateString(), 'dismissal_date' => now()->subMonth()->toDateString(),
        ]);

        $this->postJson("/api/employees/{$dismissed->id}/absences", $this->absence(
            now()->subWeek()->toDateString(), now()->subDays(2)->toDateString(),
        ))->assertUnprocessable()->assertJsonValidationErrors('start_date');
    }

    public function test_end_before_start_is_rejected(): void
    {
        $this->postJson("/api/employees/{$this->employee->id}/absences", $this->absence(
            now()->subDays(5)->toDateString(), now()->subDays(10)->toDateString(),
        ))->assertUnprocessable()->assertJsonValidationErrors('end_date');
    }

    public function test_absence_can_be_updated_and_deleted(): void
    {
        $absence = EmployeeAbsence::factory()->for($this->employee)->create([
            'start_date' => now()->subDays(20)->toDateString(), 'end_date' => now()->subDays(10)->toDateString(),
        ]);

        $this->putJson("/api/absences/{$absence->id}", $this->absence(
            now()->subDays(20)->toDateString(), now()->subDays(16)->toDateString(),
        ))->assertOk()->assertJsonPath('days', 5);

        $this->deleteJson("/api/absences/{$absence->id}")->assertNoContent();
        $this->assertModelMissing($absence);
    }

    public function test_list_by_year_and_leave_balance(): void
    {
        EmployeeAbsence::factory()->for($this->employee)->create([
            'type' => 'annual_leave', 'start_date' => '2026-03-02', 'end_date' => '2026-03-11',
        ]);
        EmployeeAbsence::factory()->for($this->employee)->create([
            'type' => 'sick_leave', 'start_date' => '2025-03-02', 'end_date' => '2025-03-04',
        ]);

        $this->getJson("/api/employees/{$this->employee->id}/absences?year=2026")->assertOk()->assertJsonCount(1);
        $this->getJson("/api/employees/{$this->employee->id}/leave-balance?year=2026")
            ->assertOk()
            ->assertJson(['entitlement' => 21, 'used' => 10, 'remaining' => 11]);
    }

    public function test_viewer_cannot_create_absence(): void
    {
        Sanctum::actingAs(User::factory()->withRole('viewer')->create(['organization_id' => $this->organization->id]));

        $this->postJson("/api/employees/{$this->employee->id}/absences", $this->absence(
            now()->subDays(5)->toDateString(), now()->subDays(1)->toDateString(),
        ))->assertForbidden();
    }

    public function test_employee_knows_when_absent(): void
    {
        EmployeeAbsence::factory()->for($this->employee)->create([
            'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
        ]);

        $this->assertTrue($this->employee->isAbsentOn(now()));
        $this->assertFalse($this->employee->isAbsentOn(now()->addDays(3)));
    }
}
