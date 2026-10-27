<?php

namespace Tests\Feature\Fuel;

use App\Models\FuelStation;
use App\Models\FuelTransaction;
use App\Models\User;
use App\Models\Waybill;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WaybillRefuelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_waybill_shows_its_active_refuels(): void
    {
        $waybill = Waybill::factory()->create(['status' => 'in_progress']);
        $station = FuelStation::factory()->create(['organization_id' => $waybill->organization_id, 'name' => 'Urganch Benzin']);
        FuelTransaction::factory()->create(['fuel_station_id' => $station->id, 'waybill_id' => $waybill->id, 'vehicle_id' => $waybill->vehicle_id, 'odometer' => 15250, 'amount' => 40]);
        FuelTransaction::factory()->create(['fuel_station_id' => $station->id, 'waybill_id' => $waybill->id, 'amount' => 10, 'cancelled_at' => now()]);
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->getJson("/api/waybills/{$waybill->id}")
            ->assertOk()
            ->assertJsonCount(1, 'refuels')
            ->assertJsonPath('refuels.0.fuel_station.name', 'Urganch Benzin')
            ->assertJsonPath('refuels.0.odometer', 15250)
            ->assertJsonPath('refuels.0.amount', '40.00');
    }
}
