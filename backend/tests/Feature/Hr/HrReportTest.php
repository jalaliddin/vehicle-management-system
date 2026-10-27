<?php

namespace Tests\Feature\Hr;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\EmploymentEvent;
use App\Models\Organization;
use App\Models\Position;
use App\Models\PpeIssue;
use App\Models\PpeItem;
use App\Models\SafetyBriefing;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Organization $organization;

    private Department $garage;

    private Department $office;

    private Position $position;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-21');
        $this->organization = Organization::factory()->create();
        $this->garage = Department::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Garaj']);
        $this->office = Department::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Ofis']);
        $this->position = Position::factory()->create();
        Sanctum::actingAs(User::factory()->withRole('superadmin')->create(['organization_id' => $this->organization->id]));
    }

    /**
     * An employee hired into the garage on 2026-01-10 and moved to the office on 2026-06-01.
     */
    private function transferredEmployee(): Employee
    {
        $employee = Employee::factory()->create([
            'organization_id' => $this->organization->id, 'department_id' => $this->office->id,
            'position_id' => $this->position->id, 'hire_date' => '2026-01-10',
        ]);
        EmploymentEvent::factory()->for($employee)->create([
            'type' => 'hire', 'effective_date' => '2026-01-10', 'organization_id' => $this->organization->id,
            'department_id' => $this->garage->id, 'position_id' => $this->position->id,
        ]);
        EmploymentEvent::factory()->for($employee)->create([
            'type' => 'transfer', 'effective_date' => '2026-06-01', 'organization_id' => $this->organization->id,
            'department_id' => $this->office->id, 'position_id' => $this->position->id,
        ]);

        return $employee;
    }

    public function test_headcount_on_a_date_uses_the_assignment_of_that_date(): void
    {
        $this->transferredEmployee();

        $this->getJson('/api/hr/reports/headcount?date=2026-03-01')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.department', 'Garaj')
            ->assertJsonPath('0.count', 1);

        $this->getJson('/api/hr/reports/headcount?date=2026-07-01')->assertOk()->assertJsonPath('0.department', 'Ofis');
        $this->getJson('/api/hr/reports/headcount?date=2025-12-01')->assertOk()->assertJsonCount(0);
    }

    public function test_headcount_ignores_cancelled_events_and_counts_legacy_employees(): void
    {
        $employee = $this->transferredEmployee();
        $employee->events()->where('type', 'transfer')->update(['cancelled_at' => now()]);
        Employee::factory()->create(['organization_id' => $this->organization->id, 'department_id' => $this->office->id, 'hire_date' => null]);

        $rows = collect($this->getJson('/api/hr/reports/headcount?date=2026-07-01')->assertOk()->json())->keyBy('department');

        $this->assertSame(1, $rows['Garaj']['count']);
        $this->assertSame(1, $rows['Ofis']['count']);
    }

    public function test_movement_lists_events_in_period(): void
    {
        $this->transferredEmployee();

        $this->getJson('/api/hr/reports/movement?from=2026-05-01&to=2026-06-30')
            ->assertOk()
            ->assertJsonPath('counts', ['hired' => 0, 'transferred' => 1, 'dismissed' => 0])
            ->assertJsonPath('transferred.0.department', 'Ofis');
    }

    public function test_movement_belongs_to_the_organization_of_the_order(): void
    {
        $other = Organization::factory()->create();
        $employee = Employee::factory()->create(['organization_id' => $other->id]);
        EmploymentEvent::factory()->for($employee)->create(['type' => 'hire', 'effective_date' => '2026-02-01', 'organization_id' => $this->organization->id]);
        EmploymentEvent::factory()->for($employee)->create(['type' => 'transfer', 'effective_date' => '2026-05-01', 'organization_id' => $other->id]);

        $this->getJson("/api/hr/reports/movement?from=2026-01-01&to=2026-06-30&organization_id={$this->organization->id}")
            ->assertOk()
            ->assertJsonPath('counts', ['hired' => 1, 'transferred' => 0, 'dismissed' => 0]);
        $this->getJson("/api/hr/reports/movement?from=2026-01-01&to=2026-06-30&organization_id={$other->id}")
            ->assertOk()
            ->assertJsonPath('counts', ['hired' => 0, 'transferred' => 1, 'dismissed' => 0]);
    }

    public function test_absent_lists_who_is_away_on_the_date(): void
    {
        $employee = Employee::factory()->create(['organization_id' => $this->organization->id]);
        EmployeeAbsence::factory()->for($employee)->create(['type' => 'sick_leave', 'start_date' => '2026-10-20', 'end_date' => '2026-10-23']);
        EmployeeAbsence::factory()->for(Employee::factory()->create(['organization_id' => $this->organization->id]))
            ->create(['start_date' => '2026-10-20', 'end_date' => '2026-10-23', 'is_planned' => true]);

        $this->getJson('/api/hr/reports/absent?date=2026-10-21')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.employee_id', $employee->id)
            ->assertJsonPath('0.type', 'sick_leave');
    }

    public function test_leave_schedule_compares_planned_and_actual(): void
    {
        $employee = Employee::factory()->create(['organization_id' => $this->organization->id, 'annual_leave_days' => 21]);
        EmployeeAbsence::factory()->for($employee)->create(['start_date' => '2026-07-01', 'end_date' => '2026-07-14', 'is_planned' => true]);
        EmployeeAbsence::factory()->for($employee)->create(['start_date' => '2026-07-01', 'end_date' => '2026-07-10']);

        $this->getJson('/api/hr/reports/leave-schedule?year=2026')
            ->assertOk()
            ->assertJsonPath('0.planned_days', 14)
            ->assertJsonPath('0.actual_days', 10)
            ->assertJsonPath('0.entitlement', 21);
    }

    public function test_briefing_journal_lists_participants(): void
    {
        $employee = Employee::factory()->create(['organization_id' => $this->organization->id, 'last_name' => 'Karimov']);
        $briefing = SafetyBriefing::factory()->create(['organization_id' => $this->organization->id, 'conducted_on' => '2026-09-01']);
        $briefing->participants()->attach($employee->id);
        SafetyBriefing::factory()->create(['organization_id' => $this->organization->id, 'conducted_on' => '2026-01-01']);

        $this->getJson('/api/hr/reports/briefing-journal?from=2026-08-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.participants.0.full_name', $employee->full_name);
    }

    public function test_ppe_issues_in_period(): void
    {
        $employee = Employee::factory()->create(['organization_id' => $this->organization->id]);
        PpeIssue::factory()->for($employee)->create(['issued_on' => '2026-09-15']);
        PpeIssue::factory()->for($employee)->create(['issued_on' => '2026-01-15']);

        $this->getJson('/api/hr/reports/ppe-issues?from=2026-09-01&to=2026-09-30')->assertOk()->assertJsonCount(1);
    }

    public function test_ppe_demand_sums_due_issues_and_uncovered_norms(): void
    {
        $boots = PpeItem::factory()->create(['kind' => 'footwear', 'name' => 'Etik']);
        $suit = PpeItem::factory()->create(['kind' => 'clothing', 'name' => 'Kombinezon']);
        $this->position->ppeNorms()->create(['ppe_item_id' => $boots->id, 'quantity' => 1, 'wear_months' => 12]);
        $this->position->ppeNorms()->create(['ppe_item_id' => $suit->id, 'quantity' => 2, 'wear_months' => 12]);

        $first = Employee::factory()->create(['organization_id' => $this->organization->id, 'position_id' => $this->position->id, 'shoe_size' => '43', 'clothing_size' => '52']);
        $second = Employee::factory()->create(['organization_id' => $this->organization->id, 'position_id' => $this->position->id, 'shoe_size' => '43', 'clothing_size' => '52']);
        PpeIssue::factory()->for($first)->create(['ppe_item_id' => $boots->id, 'size' => '43', 'due_on' => '2026-12-21']);
        PpeIssue::factory()->for($second)->create(['ppe_item_id' => $boots->id, 'size' => '43', 'due_on' => '2027-03-21']);
        PpeIssue::factory()->for($first)->create(['ppe_item_id' => $suit->id, 'size' => '52', 'quantity' => 2, 'due_on' => '2027-06-01']);

        $rows = collect($this->getJson('/api/hr/reports/ppe-demand?months=3')->assertOk()->json())->keyBy('ppe_item');

        $this->assertSame(['size' => '43', 'quantity' => 1], array_intersect_key($rows['Etik'], ['size' => 1, 'quantity' => 1]));
        $this->assertSame(['size' => '52', 'quantity' => 2], array_intersect_key($rows['Kombinezon'], ['size' => 1, 'quantity' => 1]));
    }

    public function test_reports_require_their_permission(): void
    {
        Sanctum::actingAs(User::factory()->withRole('ohs')->create(['organization_id' => $this->organization->id]));
        $this->getJson('/api/hr/reports/briefing-journal')->assertOk();
        $this->getJson('/api/hr/reports/movement')->assertOk();

        Sanctum::actingAs(User::factory()->withRole('viewer')->create(['organization_id' => $this->organization->id]));
        $this->getJson('/api/hr/reports/headcount')->assertForbidden();
        $this->getJson('/api/hr/reports/ppe-demand')->assertForbidden();
    }

    public function test_reports_are_scoped_to_users_organization(): void
    {
        Employee::factory()->create(['hire_date' => '2026-01-01']);
        Sanctum::actingAs(User::factory()->withRole('hr')->create(['organization_id' => $this->organization->id]));

        $this->getJson('/api/hr/reports/headcount?date=2026-10-21')->assertOk()->assertJsonCount(0);
    }
}
