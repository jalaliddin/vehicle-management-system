<?php

namespace Tests\Feature\Hr;

use App\Models\Driver;
use App\Models\Employee;
use App\Models\MedicalExam;
use App\Models\Organization;
use App\Models\Position;
use App\Models\PpeIssue;
use App\Models\SafetyBriefing;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrAlertTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Organization $organization;

    private Position $position;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-21');
        $this->organization = Organization::factory()->create();
        $this->position = Position::factory()->create();
        Sanctum::actingAs(User::factory()->withRole('ohs')->create(['organization_id' => $this->organization->id]));
    }

    private function employee(array $attributes = []): Employee
    {
        return Employee::factory()->create(array_merge([
            'organization_id' => $this->organization->id, 'position_id' => $this->position->id,
        ], $attributes));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function alerts(string $query = ''): array
    {
        return $this->getJson('/api/hr/alerts'.$query)->assertOk()->json();
    }

    public function test_overdue_and_due_soon_medical_exams_are_listed(): void
    {
        $overdue = $this->employee();
        MedicalExam::factory()->for($overdue)->create(['next_due_date' => '2026-10-01']);
        $soon = $this->employee();
        MedicalExam::factory()->for($soon)->create(['next_due_date' => '2026-11-05']);
        MedicalExam::factory()->for($this->employee())->create(['next_due_date' => '2027-06-01']);

        $alerts = collect($this->alerts('?category=medical'));

        $this->assertCount(2, $alerts);
        $this->assertSame(['overdue', -20], [$alerts[0]['state'], $alerts[0]['days_left']]);
        $this->assertSame($overdue->id, $alerts[0]['employee_id']);
        $this->assertSame(['due_soon', 15], [$alerts[1]['state'], $alerts[1]['days_left']]);
    }

    public function test_missing_required_exam_counts_as_overdue(): void
    {
        $this->position->update(['medical_exam_months' => 12]);
        $employee = $this->employee();
        Employee::factory()->create(['organization_id' => $this->organization->id]);

        $alerts = $this->alerts('?category=medical');

        $this->assertCount(1, $alerts);
        $this->assertSame([$employee->id, 'overdue', null], [$alerts[0]['employee_id'], $alerts[0]['state'], $alerts[0]['due_date']]);
    }

    public function test_unfit_exam_is_an_alert(): void
    {
        MedicalExam::factory()->for($this->employee())->create(['result' => 'unfit', 'next_due_date' => null]);

        $this->assertSame('overdue', $this->alerts('?category=medical')[0]['state']);
    }

    public function test_briefing_alerts_use_latest_participation_and_missing(): void
    {
        $this->position->update(['briefing_months' => 6]);
        $renewed = $this->employee();
        $never = $this->employee();
        $old = SafetyBriefing::factory()->create(['organization_id' => $this->organization->id]);
        $old->participants()->attach($renewed->id, ['next_due_date' => '2026-05-01']);
        $new = SafetyBriefing::factory()->create(['organization_id' => $this->organization->id]);
        $new->participants()->attach($renewed->id, ['next_due_date' => '2027-03-01']);

        $alerts = $this->alerts('?category=briefing');

        $this->assertCount(1, $alerts);
        $this->assertSame($never->id, $alerts[0]['employee_id']);
    }

    public function test_ppe_alerts(): void
    {
        $employee = $this->employee();
        PpeIssue::factory()->for($employee)->create(['due_on' => '2026-11-01']);
        PpeIssue::factory()->for($employee)->create(['due_on' => '2026-09-01', 'status' => 'replaced']);

        $alerts = $this->alerts('?category=ppe');

        $this->assertCount(1, $alerts);
        $this->assertSame('due_soon', $alerts[0]['state']);
    }

    public function test_license_alerts(): void
    {
        $employee = $this->employee();
        Driver::factory()->create([
            'organization_id' => $this->organization->id, 'employee_id' => $employee->id, 'license_expiry' => '2026-10-10',
        ]);

        $alerts = $this->alerts('?category=license');

        $this->assertCount(1, $alerts);
        $this->assertSame(['overdue', $employee->id], [$alerts[0]['state'], $alerts[0]['employee_id']]);
    }

    public function test_dismissed_employees_are_excluded(): void
    {
        $this->position->update(['medical_exam_months' => 12, 'briefing_months' => 6]);
        $dismissed = $this->employee(['status' => 'dismissed']);
        PpeIssue::factory()->for($dismissed)->create(['due_on' => '2026-10-01']);
        Driver::factory()->create([
            'organization_id' => $this->organization->id, 'employee_id' => $dismissed->id, 'license_expiry' => '2026-10-10',
        ]);

        $this->assertSame([], $this->alerts());
    }

    public function test_alerts_are_scoped_to_users_organization(): void
    {
        MedicalExam::factory()->for(Employee::factory()->create())->create(['next_due_date' => '2026-10-01']);

        $this->assertSame([], $this->alerts());
    }

    public function test_user_without_organization_gets_empty_alerts(): void
    {
        MedicalExam::factory()->for($this->employee())->create(['next_due_date' => '2026-10-01']);
        Sanctum::actingAs(User::factory()->withRole('ohs')->create(['organization_id' => null]));

        $this->assertSame([], $this->alerts());
    }

    public function test_summary_counts_by_category(): void
    {
        MedicalExam::factory()->for($this->employee())->create(['next_due_date' => '2026-10-01']);
        MedicalExam::factory()->for($this->employee())->create(['next_due_date' => '2026-11-01']);
        PpeIssue::factory()->for($this->employee())->create(['due_on' => '2026-10-01']);

        $this->getJson('/api/hr/alerts/summary')
            ->assertOk()
            ->assertExactJson([
                'medical' => ['overdue' => 1, 'due_soon' => 1],
                'briefing' => ['overdue' => 0, 'due_soon' => 0],
                'ppe' => ['overdue' => 1, 'due_soon' => 0],
                'license' => ['overdue' => 0, 'due_soon' => 0],
            ]);
    }

    public function test_viewer_cannot_see_alerts(): void
    {
        Sanctum::actingAs(User::factory()->withRole('viewer')->create(['organization_id' => $this->organization->id]));

        $this->getJson('/api/hr/alerts')->assertForbidden();
    }
}
