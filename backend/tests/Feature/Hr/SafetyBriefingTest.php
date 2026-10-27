<?php

namespace Tests\Feature\Hr;

use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Position;
use App\Models\SafetyBriefing;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SafetyBriefingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Organization $organization;

    private Employee $driver;

    private Employee $clerk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->driver = Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'position_id' => Position::factory()->withPeriodicities(12, 6)->create()->id,
        ]);
        $this->clerk = Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'position_id' => Position::factory()->withPeriodicities(12, 12)->create()->id,
        ]);
        Sanctum::actingAs(User::factory()->withRole('ohs')->create(['organization_id' => $this->organization->id]));
    }

    /**
     * @return array<string, mixed>
     */
    private function briefing(array $overrides = []): array
    {
        return array_merge([
            'organization_id' => $this->organization->id,
            'type' => 'repeated',
            'conducted_on' => '2026-01-10',
            'topic' => "Yo'l harakati xavfsizligi",
            'instructor_name' => 'Mehnat muhofazasi muhandisi',
            'participant_ids' => [$this->driver->id, $this->clerk->id],
        ], $overrides);
    }

    public function test_group_briefing_sets_next_due_per_participant_position(): void
    {
        $id = $this->postJson('/api/safety-briefings', $this->briefing())->assertCreated()->json('id');

        $this->getJson("/api/safety-briefings/{$id}")
            ->assertOk()
            ->assertJsonCount(2, 'participants');

        $this->assertSame('2026-07-10', SafetyBriefing::find($id)->participants()->find($this->driver->id)->pivot->next_due_date);
        $this->assertSame('2027-01-10', SafetyBriefing::find($id)->participants()->find($this->clerk->id)->pivot->next_due_date);
    }

    public function test_targeted_briefing_sets_no_next_due(): void
    {
        $id = $this->postJson('/api/safety-briefings', $this->briefing(['type' => 'targeted']))->assertCreated()->json('id');

        $this->assertNull(SafetyBriefing::find($id)->participants()->find($this->driver->id)->pivot->next_due_date);
    }

    public function test_dismissed_or_foreign_employee_cannot_participate(): void
    {
        $dismissed = Employee::factory()->dismissed()->create(['organization_id' => $this->organization->id]);
        $foreign = Employee::factory()->create();

        $this->postJson('/api/safety-briefings', $this->briefing(['participant_ids' => [$this->driver->id, $dismissed->id]]))
            ->assertUnprocessable()->assertJsonValidationErrors('participant_ids');
        $this->postJson('/api/safety-briefings', $this->briefing(['participant_ids' => [$foreign->id]]))
            ->assertUnprocessable()->assertJsonValidationErrors('participant_ids');
    }

    public function test_participants_are_required(): void
    {
        $this->postJson('/api/safety-briefings', $this->briefing(['participant_ids' => []]))
            ->assertUnprocessable()->assertJsonValidationErrors('participant_ids');
    }

    public function test_changing_date_recomputes_next_due(): void
    {
        $id = $this->postJson('/api/safety-briefings', $this->briefing())->assertCreated()->json('id');

        $this->putJson("/api/safety-briefings/{$id}", $this->briefing(['conducted_on' => '2026-02-10']))->assertOk();

        $this->assertSame('2026-08-10', SafetyBriefing::find($id)->participants()->find($this->driver->id)->pivot->next_due_date);
    }

    public function test_participant_changes_are_audited(): void
    {
        $id = $this->postJson('/api/safety-briefings', $this->briefing())->assertCreated()->json('id');

        $this->putJson("/api/safety-briefings/{$id}", $this->briefing(['participant_ids' => [$this->driver->id]]))->assertOk();

        $entry = AuditLog::where('event', 'participants_synced')->where('auditable_id', $id)->latest('id')->firstOrFail();
        $this->assertSame([$this->driver->id], $entry->new_values['participant_ids']);
        $this->assertEqualsCanonicalizing([$this->driver->id, $this->clerk->id], $entry->old_values['participant_ids']);
    }

    public function test_list_filters_and_paginates(): void
    {
        $this->postJson('/api/safety-briefings', $this->briefing())->assertCreated();
        $this->postJson('/api/safety-briefings', $this->briefing(['type' => 'targeted', 'conducted_on' => '2026-03-01']))->assertCreated();

        $this->getJson('/api/safety-briefings?type=targeted')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.participants_count', 2);
        $this->getJson('/api/safety-briefings?from=2026-02-01')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_list_filters_by_participant(): void
    {
        $this->postJson('/api/safety-briefings', $this->briefing())->assertCreated();
        $this->postJson('/api/safety-briefings', $this->briefing(['participant_ids' => [$this->clerk->id]]))->assertCreated();

        $this->getJson("/api/safety-briefings?employee_id={$this->driver->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_briefing_can_be_deleted(): void
    {
        $id = $this->postJson('/api/safety-briefings', $this->briefing())->assertCreated()->json('id');

        $this->deleteJson("/api/safety-briefings/{$id}")->assertNoContent();
        $this->assertDatabaseCount('safety_briefing_participants', 0);
    }

    public function test_ohs_view_cannot_create_briefing(): void
    {
        Sanctum::actingAs(User::factory()->withPermissions(Permission::OhsView)->create(['organization_id' => $this->organization->id]));

        $this->getJson('/api/safety-briefings')->assertOk();
        $this->postJson('/api/safety-briefings', $this->briefing())->assertForbidden();
    }

    public function test_briefings_of_other_organizations_are_hidden(): void
    {
        SafetyBriefing::factory()->create();

        $this->getJson('/api/safety-briefings')->assertOk()->assertJsonCount(0, 'data');
    }
}
