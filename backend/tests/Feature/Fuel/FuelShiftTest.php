<?php

namespace Tests\Feature\Fuel;

use App\Models\FuelShift;
use App\Models\FuelStation;
use App\Models\FuelTransaction;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FuelShiftTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FuelStation $station;

    private User $fueler;

    protected function setUp(): void
    {
        parent::setUp();

        $organization = Organization::factory()->create();
        $this->station = FuelStation::factory()->create(['organization_id' => $organization->id, 'current_balance' => 3200]);
        $this->fueler = User::factory()->withRole('fueler')->create(['organization_id' => $organization->id]);
        $this->station->fuelers()->attach($this->fueler);
        Sanctum::actingAs($this->fueler);
    }

    private function open(string|int $measured = 3200): int
    {
        return $this->postJson("/api/fuel/stations/{$this->station->id}/shifts", ['opening_measured' => $measured])
            ->assertCreated()->json('id');
    }

    public function test_open_shift_records_book_and_measured_balance(): void
    {
        $this->postJson("/api/fuel/stations/{$this->station->id}/shifts", ['opening_measured' => 3190])
            ->assertCreated()
            ->assertJsonPath('opening_balance', '3200.00')
            ->assertJsonPath('opening_measured', '3190.00')
            ->assertJsonPath('status', 'open');

        $this->getJson("/api/fuel/stations/{$this->station->id}/current-shift")
            ->assertOk()
            ->assertJsonPath('shift.opened_by.id', $this->fueler->id);
    }

    public function test_second_open_shift_is_rejected(): void
    {
        $this->open();

        $this->postJson("/api/fuel/stations/{$this->station->id}/shifts", ['opening_measured' => 3200])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Bu shahobchada ochiq smena bor');
    }

    public function test_close_with_discrepancy_requires_note(): void
    {
        $id = $this->open();

        $this->postJson("/api/fuel/shifts/{$id}/close", ['closing_measured' => 3195])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['discrepancy_note' => 'Farq izohini yozing']);
    }

    public function test_close_records_discrepancy(): void
    {
        $id = $this->open();

        $this->postJson("/api/fuel/shifts/{$id}/close", ['closing_measured' => 3195, 'discrepancy_note' => "Bug'lanish"])
            ->assertOk()
            ->assertJsonPath('status', 'closed')
            ->assertJsonPath('closing_balance', '3200.00')
            ->assertJsonPath('discrepancy', '-5.00');
    }

    public function test_close_without_discrepancy_needs_no_note(): void
    {
        $id = $this->open();

        $this->postJson("/api/fuel/shifts/{$id}/close", ['closing_measured' => '3200.00'])
            ->assertOk()
            ->assertJsonPath('discrepancy', '0.00');
    }

    public function test_closed_shift_cannot_be_closed_again(): void
    {
        $id = $this->open();
        $this->postJson("/api/fuel/shifts/{$id}/close", ['closing_measured' => 3200])->assertOk();

        $this->postJson("/api/fuel/shifts/{$id}/close", ['closing_measured' => 3200])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Smena yopilgan');
    }

    public function test_shift_report_totals_exclude_cancelled_operations(): void
    {
        $shift = FuelShift::factory()->create(['fuel_station_id' => $this->station->id, 'opened_by' => $this->fueler->id]);
        FuelTransaction::factory()->receipt(100)->create(['fuel_station_id' => $this->station->id, 'fuel_shift_id' => $shift->id]);
        FuelTransaction::factory()->create(['fuel_station_id' => $this->station->id, 'fuel_shift_id' => $shift->id, 'amount' => 40]);
        FuelTransaction::factory()->create(['fuel_station_id' => $this->station->id, 'fuel_shift_id' => $shift->id, 'amount' => 10, 'cancelled_at' => now()]);

        $this->getJson("/api/fuel/shifts/{$shift->id}")
            ->assertOk()
            ->assertJsonPath('totals', ['receipts' => '100.00', 'refuels' => '40.00'])
            ->assertJsonCount(3, 'operations');
    }

    public function test_shift_list_filters_discrepancies(): void
    {
        FuelShift::factory()->closed('3200.00')->create(['fuel_station_id' => $this->station->id, 'opened_by' => $this->fueler->id]);
        FuelShift::factory()->closed('3190.00', 'Kam')->create(['fuel_station_id' => $this->station->id, 'opened_by' => $this->fueler->id]);

        $this->getJson('/api/fuel/shifts')->assertOk()->assertJsonPath('total', 2);
        $this->getJson('/api/fuel/shifts?with_discrepancy=1')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.discrepancy', '-10.00');
    }

    public function test_shift_list_accepts_true_for_discrepancy_filter(): void
    {
        FuelShift::factory()->closed('3200.00')->create(['fuel_station_id' => $this->station->id, 'opened_by' => $this->fueler->id]);
        FuelShift::factory()->closed('3190.00', 'Kam')->create(['fuel_station_id' => $this->station->id, 'opened_by' => $this->fueler->id]);

        $this->getJson('/api/fuel/shifts?with_discrepancy=true')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/fuel/shifts?with_discrepancy=false')->assertOk()->assertJsonPath('total', 2);
    }

    public function test_unassigned_fueler_cannot_open_shift_on_other_station(): void
    {
        $other = FuelStation::factory()->create(['organization_id' => $this->station->organization_id]);

        $this->postJson("/api/fuel/stations/{$other->id}/shifts", ['opening_measured' => 100])->assertForbidden();
    }
}
