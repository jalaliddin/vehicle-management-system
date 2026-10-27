<?php

namespace Tests\Feature\Hr;

use App\Models\Driver;
use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\MedicalExam;
use App\Models\Organization;
use App\Models\Position;
use App\Models\SafetyBriefing;
use App\Models\User;
use App\Services\Hr\DriverClearance;
use Closure;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DriverClearanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-21');
        $this->organization = Organization::factory()->create();
    }

    /**
     * A driver whose license, medical exam and briefing are all in order.
     */
    private function admittedDriver(): Driver
    {
        $employee = Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'position_id' => Position::factory()->withPeriodicities(12, 6)->create()->id,
        ]);
        MedicalExam::factory()->for($employee)->create(['exam_date' => '2026-06-01', 'next_due_date' => '2027-06-01']);
        SafetyBriefing::factory()->create(['organization_id' => $this->organization->id])
            ->participants()->attach($employee->id, ['next_due_date' => '2027-01-01']);

        return Driver::factory()->create([
            'organization_id' => $this->organization->id, 'employee_id' => $employee->id, 'license_expiry' => '2028-01-01',
        ]);
    }

    public function test_driver_with_valid_records_is_admitted(): void
    {
        $this->assertSame(['admitted' => true, 'reasons' => []], app(DriverClearance::class)->check($this->admittedDriver()));
    }

    /**
     * @return array<string, array{string, Closure(Driver): void}>
     */
    public static function reasons(): array
    {
        return [
            'no employee' => ['no_employee', fn (Driver $d) => $d->update(['employee_id' => null])],
            'dismissed' => ['employee_dismissed', fn (Driver $d) => $d->employee->update(['status' => 'dismissed'])],
            'inactive driver' => ['driver_inactive', fn (Driver $d) => $d->update(['status' => 'inactive'])],
            'license expired' => ['license_expired', fn (Driver $d) => $d->update(['license_expiry' => '2026-10-01'])],
            'no exam' => ['medical_missing', fn (Driver $d) => $d->employee->medicalExams()->delete()],
            'exam overdue' => ['medical_overdue', fn (Driver $d) => $d->employee->medicalExams()->update(['next_due_date' => '2026-10-01'])],
            'unfit' => ['medical_unfit', fn (Driver $d) => $d->employee->medicalExams()->update(['result' => 'unfit'])],
            'briefing overdue' => ['briefing_overdue', fn (Driver $d) => $d->employee->briefings()->updateExistingPivot(
                $d->employee->briefings()->value('safety_briefings.id'), ['next_due_date' => '2026-10-01'],
            )],
            'absent' => ['absent', fn (Driver $d) => EmployeeAbsence::factory()->for($d->employee)->create([
                'type' => 'sick_leave', 'start_date' => '2026-10-20', 'end_date' => '2026-10-25',
            ])],
        ];
    }

    #[DataProvider('reasons')]
    public function test_each_reason_is_reported(string $code, Closure $breakIt): void
    {
        $driver = $this->admittedDriver();
        $breakIt($driver);

        $result = app(DriverClearance::class)->check($driver->fresh());

        $this->assertFalse($result['admitted']);
        $this->assertContains($code, array_column($result['reasons'], 'code'));
    }

    public function test_exam_without_due_date_falls_due_by_the_positions_periodicity(): void
    {
        $driver = $this->admittedDriver();
        $driver->employee->medicalExams()->update(['exam_date' => '2025-01-10', 'next_due_date' => null]);

        $this->assertContains('medical_overdue', array_column(app(DriverClearance::class)->check($driver->fresh())['reasons'], 'code'));
    }

    public function test_driver_show_embeds_clearance(): void
    {
        $driver = $this->admittedDriver();
        Sanctum::actingAs(User::factory()->withRole('dispatcher')->create(['organization_id' => $this->organization->id]));

        $this->getJson("/api/drivers/{$driver->id}")->assertOk()->assertJsonPath('clearance.admitted', true);
        $this->getJson("/api/drivers/{$driver->id}/clearance")->assertOk()->assertJsonPath('admitted', true);
    }
}
