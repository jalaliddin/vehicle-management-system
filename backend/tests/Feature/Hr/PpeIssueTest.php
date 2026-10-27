<?php

namespace Tests\Feature\Hr;

use App\Enums\Permission;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Position;
use App\Models\PpeIssue;
use App\Models\PpeItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PpeIssueTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Organization $organization;

    private Employee $employee;

    private PpeItem $boots;

    private PpeItem $gloves;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $position = Position::factory()->create();
        $this->boots = PpeItem::factory()->create(['default_wear_months' => 24]);
        $this->gloves = PpeItem::factory()->create(['default_wear_months' => 2]);
        $position->ppeNorms()->create(['ppe_item_id' => $this->boots->id, 'quantity' => 1, 'wear_months' => 12]);
        $this->employee = Employee::factory()->create(['organization_id' => $this->organization->id, 'position_id' => $position->id]);
        Sanctum::actingAs(User::factory()->withRole('ohs')->create(['organization_id' => $this->organization->id]));
    }

    private function issue(PpeItem $item, string $issuedOn = '2026-01-10'): int
    {
        return $this->postJson("/api/employees/{$this->employee->id}/ppe-issues", [
            'ppe_item_id' => $item->id, 'quantity' => 1, 'size' => '43', 'issued_on' => $issuedOn,
        ])->assertCreated()->json('id');
    }

    public function test_due_on_uses_position_norm_then_item_default(): void
    {
        $this->assertSame('2027-01-10', PpeIssue::find($this->issue($this->boots))->due_on->toDateString());
        $this->assertSame('2026-03-10', PpeIssue::find($this->issue($this->gloves))->due_on->toDateString());
    }

    public function test_due_on_is_null_without_norm_or_default(): void
    {
        $helmet = PpeItem::factory()->create(['default_wear_months' => null]);

        $this->assertNull(PpeIssue::find($this->issue($helmet))->due_on);
    }

    public function test_reissue_replaces_previous_in_use_issue(): void
    {
        $first = $this->issue($this->boots, '2025-01-10');
        $this->issue($this->boots, '2026-01-10');

        $old = PpeIssue::find($first);
        $this->assertSame('replaced', $old->status);
        $this->assertSame('2026-01-10', $old->closed_on->toDateString());
        $this->assertSame(1, PpeIssue::where('status', 'in_use')->count());
    }

    public function test_future_issue_date_is_rejected(): void
    {
        $this->postJson("/api/employees/{$this->employee->id}/ppe-issues", [
            'ppe_item_id' => $this->boots->id, 'quantity' => 1, 'issued_on' => now()->addDay()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('issued_on');
    }

    public function test_card_shows_uncovered_norms(): void
    {
        $this->getJson("/api/employees/{$this->employee->id}/ppe")
            ->assertOk()
            ->assertJsonPath('norms.0.ppe_item.id', $this->boots->id)
            ->assertJsonPath('norms.0.current_issue', null);

        $id = $this->issue($this->boots);

        $this->getJson("/api/employees/{$this->employee->id}/ppe")
            ->assertOk()
            ->assertJsonPath('norms.0.current_issue.id', $id)
            ->assertJsonCount(1, 'issues');
    }

    public function test_close_marks_returned(): void
    {
        $id = $this->issue($this->boots);

        $this->postJson("/api/ppe-issues/{$id}/close", ['status' => 'returned', 'closed_on' => '2026-05-01'])
            ->assertOk()
            ->assertJsonPath('status', 'returned');

        $this->assertSame('2026-05-01', PpeIssue::find($id)->closed_on->toDateString());
    }

    public function test_closing_twice_is_rejected(): void
    {
        $id = $this->issue($this->boots);
        $this->postJson("/api/ppe-issues/{$id}/close", ['status' => 'written_off', 'closed_on' => '2026-05-01'])->assertOk();

        $this->postJson("/api/ppe-issues/{$id}/close", ['status' => 'returned', 'closed_on' => '2026-05-02'])
            ->assertUnprocessable();
    }

    public function test_issued_item_cannot_be_deleted_from_catalog(): void
    {
        $this->issue($this->gloves);

        $this->deleteJson("/api/ppe-items/{$this->gloves->id}")->assertUnprocessable();
    }

    public function test_ohs_view_cannot_issue(): void
    {
        Sanctum::actingAs(User::factory()->withPermissions(Permission::OhsView)->create(['organization_id' => $this->organization->id]));

        $this->getJson("/api/employees/{$this->employee->id}/ppe")->assertOk();
        $this->postJson("/api/employees/{$this->employee->id}/ppe-issues", [
            'ppe_item_id' => $this->boots->id, 'quantity' => 1, 'issued_on' => '2026-01-10',
        ])->assertForbidden();
    }
}
