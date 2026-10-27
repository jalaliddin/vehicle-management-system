<?php

namespace Tests\Feature\Hr;

use App\Enums\Permission;
use App\Models\Employee;
use App\Models\MedicalExam;
use App\Models\Organization;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MedicalExamTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Organization $organization;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->organization = Organization::factory()->create();
        $this->employee = Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'position_id' => Position::factory()->withPeriodicities(12, 6)->create()->id,
        ]);
    }

    private function actingAsOhs(): void
    {
        Sanctum::actingAs(User::factory()->withRole('ohs')->create(['organization_id' => $this->organization->id]));
    }

    private function actingWith(Permission ...$permissions): void
    {
        Sanctum::actingAs(User::factory()->withPermissions(...$permissions)->create(['organization_id' => $this->organization->id]));
    }

    public function test_next_due_defaults_from_position_periodicity(): void
    {
        $this->actingAsOhs();

        $this->postJson("/api/employees/{$this->employee->id}/medical-exams", [
            'type' => 'periodic', 'exam_date' => '2026-01-10', 'result' => 'fit',
        ])->assertCreated()->assertJsonPath('next_due_date', '2027-01-10');
    }

    public function test_explicit_next_due_overrides_default(): void
    {
        $this->actingAsOhs();

        $this->postJson("/api/employees/{$this->employee->id}/medical-exams", [
            'type' => 'periodic', 'exam_date' => '2026-01-10', 'result' => 'fit', 'next_due_date' => '2026-07-10',
        ])->assertCreated()->assertJsonPath('next_due_date', '2026-07-10');
    }

    public function test_status_reflects_result_and_due_date(): void
    {
        $this->travelTo('2026-10-21');

        $this->assertSame('valid', MedicalExam::factory()->make(['next_due_date' => '2027-01-01', 'result' => 'fit'])->status());
        $this->assertSame('due_soon', MedicalExam::factory()->make(['next_due_date' => '2026-11-10', 'result' => 'fit'])->status());
        $this->assertSame('overdue', MedicalExam::factory()->make(['next_due_date' => '2026-10-20', 'result' => 'fit'])->status());
        $this->assertSame('unfit', MedicalExam::factory()->make(['next_due_date' => null, 'result' => 'unfit'])->status());
        $this->assertSame('valid', MedicalExam::factory()->make(['next_due_date' => null, 'result' => 'fit'])->status());
    }

    public function test_list_shows_the_due_date_from_the_positions_periodicity(): void
    {
        MedicalExam::factory()->for($this->employee)->create(['exam_date' => '2026-01-10', 'next_due_date' => null]);
        $this->actingWith(Permission::OhsView);

        $this->getJson("/api/employees/{$this->employee->id}/medical-exams")
            ->assertOk()
            ->assertJsonPath('0.next_due_date', '2027-01-10');
    }

    public function test_user_without_medical_view_gets_no_medical_details(): void
    {
        MedicalExam::factory()->for($this->employee)->create(['result' => 'fit_with_restrictions', 'restrictions' => 'Tungi ish taqiqlangan']);
        $this->actingWith(Permission::OhsView);

        $response = $this->getJson("/api/employees/{$this->employee->id}/medical-exams")->assertOk();

        $this->assertArrayNotHasKey('result', $response->json('0'));
        $this->assertArrayNotHasKey('restrictions', $response->json('0'));
        $this->assertArrayHasKey('status', $response->json('0'));
        $this->assertArrayHasKey('next_due_date', $response->json('0'));
    }

    public function test_medical_view_sees_details(): void
    {
        MedicalExam::factory()->for($this->employee)->create(['restrictions' => 'Tungi ish taqiqlangan']);
        $this->actingWith(Permission::OhsMedicalView);

        $this->getJson("/api/employees/{$this->employee->id}/medical-exams")
            ->assertOk()
            ->assertJsonPath('0.restrictions', 'Tungi ish taqiqlangan');
    }

    public function test_document_download_requires_medical_view(): void
    {
        $this->actingAsOhs();
        $id = $this->post("/api/employees/{$this->employee->id}/medical-exams", [
            'type' => 'periodic', 'exam_date' => '2026-01-10', 'result' => 'fit',
            'document' => UploadedFile::fake()->create('xulosa.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('id');

        $this->actingWith(Permission::OhsView);
        $this->get("/api/medical-exams/{$id}/document", ['Accept' => 'application/json'])->assertForbidden();

        $this->actingWith(Permission::OhsMedicalView);
        $this->get("/api/medical-exams/{$id}/document")->assertOk();
    }

    public function test_document_must_be_pdf_or_image(): void
    {
        $this->actingAsOhs();

        $this->post("/api/employees/{$this->employee->id}/medical-exams", [
            'type' => 'periodic', 'exam_date' => '2026-01-10', 'result' => 'fit',
            'document' => UploadedFile::fake()->create('xulosa.exe', 10, 'application/octet-stream'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('document');
    }

    public function test_deleting_exam_removes_document(): void
    {
        $this->actingAsOhs();
        $id = $this->post("/api/employees/{$this->employee->id}/medical-exams", [
            'type' => 'periodic', 'exam_date' => '2026-01-10', 'result' => 'fit',
            'document' => UploadedFile::fake()->create('xulosa.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->json('id');
        $path = MedicalExam::find($id)->document_path;
        Storage::disk('local')->assertExists($path);

        $this->deleteJson("/api/medical-exams/{$id}")->assertNoContent();

        Storage::disk('local')->assertMissing($path);
    }

    public function test_future_exam_date_is_rejected(): void
    {
        $this->actingAsOhs();

        $this->postJson("/api/employees/{$this->employee->id}/medical-exams", [
            'type' => 'periodic', 'exam_date' => now()->addDay()->toDateString(), 'result' => 'fit',
        ])->assertUnprocessable()->assertJsonValidationErrors('exam_date');
    }

    public function test_exam_can_be_updated(): void
    {
        $this->actingAsOhs();
        $exam = MedicalExam::factory()->for($this->employee)->create(['exam_date' => '2026-01-10', 'next_due_date' => '2027-01-10']);

        $this->putJson("/api/medical-exams/{$exam->id}", ['type' => 'periodic', 'exam_date' => '2026-02-10', 'result' => 'fit'])
            ->assertOk()
            ->assertJsonPath('next_due_date', '2027-02-10');
    }

    public function test_ohs_view_cannot_create_exam(): void
    {
        $this->actingWith(Permission::OhsView);

        $this->postJson("/api/employees/{$this->employee->id}/medical-exams", [
            'type' => 'periodic', 'exam_date' => '2026-01-10', 'result' => 'fit',
        ])->assertForbidden();
    }

    public function test_org_list_returns_overdue_latest_exams_only(): void
    {
        $this->travelTo('2026-10-21');
        $renewed = Employee::factory()->create(['organization_id' => $this->organization->id]);
        MedicalExam::factory()->for($renewed)->create(['exam_date' => '2025-01-01', 'next_due_date' => '2026-01-01']);
        MedicalExam::factory()->for($renewed)->create(['exam_date' => '2026-01-02', 'next_due_date' => '2027-01-02']);
        MedicalExam::factory()->for($this->employee)->create(['exam_date' => '2025-02-01', 'next_due_date' => '2026-02-01']);
        $this->actingWith(Permission::OhsView);

        $this->getJson('/api/medical-exams?state=overdue')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.employee_id', $this->employee->id);
    }
}
