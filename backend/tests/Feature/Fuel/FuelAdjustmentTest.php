<?php

namespace Tests\Feature\Fuel;

use App\Enums\Permission;
use App\Models\FuelShift;
use App\Models\FuelStation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FuelAdjustmentTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FuelStation $station;

    protected function setUp(): void
    {
        parent::setUp();

        $organization = Organization::factory()->create();
        $this->station = FuelStation::factory()->create(['organization_id' => $organization->id, 'current_balance' => 3200]);
        Sanctum::actingAs(User::factory()->withPermissions(Permission::FuelView, Permission::FuelAdjust)->create(['organization_id' => $organization->id]));
    }

    private function adjust(string|int $actual): TestResponse
    {
        return $this->postJson("/api/fuel/stations/{$this->station->id}/adjustments", [
            'actual_balance' => $actual, 'document_number' => 'INV-3', 'notes' => 'Oylik inventarizatsiya',
        ]);
    }

    public function test_adjustment_sets_book_balance_to_actual(): void
    {
        $this->adjust('3180.50')->assertCreated()
            ->assertJsonPath('kind', 'adjustment')
            ->assertJsonPath('type', 'out')
            ->assertJsonPath('amount', '19.50');

        $this->assertSame('3180.50', $this->station->fresh()->current_balance);

        $this->adjust(3200)->assertCreated()->assertJsonPath('type', 'in')->assertJsonPath('amount', '19.50');
    }

    public function test_adjustment_during_open_shift_is_rejected(): void
    {
        FuelShift::factory()->create(['fuel_station_id' => $this->station->id]);

        $this->adjust(3100)->assertUnprocessable()->assertJsonPath('message', "Inventarizatsiya smenalar orasida o'tkaziladi");
    }

    public function test_zero_difference_is_rejected(): void
    {
        $this->adjust(3200)->assertUnprocessable()->assertJsonValidationErrors('actual_balance');
    }

    public function test_adjustment_needs_fuel_adjust_permission(): void
    {
        $fueler = User::factory()->withRole('fueler')->create(['organization_id' => $this->station->organization_id]);
        Sanctum::actingAs($fueler);

        $this->adjust(3100)->assertForbidden();
    }
}
