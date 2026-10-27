<?php

namespace Tests\Feature\Fuel;

use App\Models\Driver;
use App\Models\FuelShift;
use App\Models\FuelStation;
use App\Models\FuelTransaction;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FuelReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Organization $organization;

    private FuelStation $station;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-28 12:00');
        $this->organization = Organization::factory()->create();
        $this->station = FuelStation::factory()->create(['organization_id' => $this->organization->id, 'current_balance' => 0]);
        Sanctum::actingAs(User::factory()->withRole('hq_dispatcher')->create(['organization_id' => $this->organization->id]));
    }

    private function row(array $attributes): FuelTransaction
    {
        $transaction = FuelTransaction::factory()->create(array_merge(['fuel_station_id' => $this->station->id], $attributes));
        $transaction->forceFill(['created_at' => $attributes['created_at'] ?? '2026-10-10 09:00'])->save();

        return $transaction;
    }

    private function refuel(Vehicle $vehicle, int $odometer, string $amount, ?Driver $driver = null): void
    {
        $this->row(['vehicle_id' => $vehicle->id, 'odometer' => $odometer, 'amount' => $amount, 'driver_id' => $driver?->id]);
    }

    public function test_turnover_opening_and_closing_balances(): void
    {
        $this->row(['kind' => 'adjustment', 'type' => 'in', 'amount' => 1000, 'created_at' => '2026-09-15 08:00']);
        $this->row(['kind' => 'receipt', 'type' => 'in', 'amount' => 500]);
        $this->row(['kind' => 'refuel', 'type' => 'out', 'amount' => 200]);
        $this->row(['kind' => 'refuel', 'type' => 'out', 'amount' => 50, 'cancelled_at' => now()]);
        $this->row(['kind' => 'adjustment', 'type' => 'out', 'amount' => 10]);

        $this->getJson('/api/fuel/reports/turnover?from=2026-10-01&to=2026-10-31')
            ->assertOk()
            ->assertJsonPath('0.opening', '1000.00')
            ->assertJsonPath('0.receipts', '500.00')
            ->assertJsonPath('0.refuels', '200.00')
            ->assertJsonPath('0.adjustments_in', '0.00')
            ->assertJsonPath('0.adjustments_out', '10.00')
            ->assertJsonPath('0.closing', '1290.00');
    }

    public function test_refuels_report_totals_by_vehicle_and_driver(): void
    {
        $first = Vehicle::factory()->create(['organization_id' => $this->organization->id]);
        $second = Vehicle::factory()->create(['organization_id' => $this->organization->id]);
        $driver = Driver::factory()->create(['organization_id' => $this->organization->id]);
        $this->refuel($first, 100, '40', $driver);
        $this->refuel($first, 300, '20', $driver);
        $this->refuel($second, 500, '15.5');

        $response = $this->getJson('/api/fuel/reports/refuels?from=2026-10-01&to=2026-10-31')->assertOk()
            ->assertJsonCount(3, 'rows')
            ->assertJsonPath('total', '75.50');

        $byVehicle = collect($response->json('by_vehicle'))->keyBy('vehicle_id');
        $this->assertSame('60.00', $byVehicle[$first->id]['amount']);
        $this->assertSame('60.00', collect($response->json('by_driver'))->firstWhere('driver_id', $driver->id)['amount']);
    }

    public function test_consumption_flags_over_norm(): void
    {
        $thirsty = Vehicle::factory()->create(['organization_id' => $this->organization->id, 'fuel_norm' => 10]);
        $normal = Vehicle::factory()->create(['organization_id' => $this->organization->id, 'fuel_norm' => 10]);
        $this->refuel($thirsty, 10000, '40');
        $this->refuel($thirsty, 10400, '46');
        $this->refuel($normal, 20000, '40');
        $this->refuel($normal, 20400, '42');

        $rows = collect($this->getJson('/api/fuel/reports/consumption?from=2026-10-01&to=2026-10-31')->assertOk()->json())->keyBy('vehicle_id');

        $this->assertEquals([11.5, 15, true], [$rows[$thirsty->id]['per_100km'], $rows[$thirsty->id]['deviation_percent'], $rows[$thirsty->id]['over_norm']]);
        $this->assertEquals([10.5, false], [$rows[$normal->id]['per_100km'], $rows[$normal->id]['over_norm']]);
    }

    public function test_consumption_skips_vehicle_with_single_refuel(): void
    {
        $this->refuel(Vehicle::factory()->create(['organization_id' => $this->organization->id]), 10000, '40');

        $this->getJson('/api/fuel/reports/consumption?from=2026-10-01&to=2026-10-31')->assertOk()->assertJsonCount(0);
    }

    public function test_discrepancies_lists_only_nonzero_closed_shifts(): void
    {
        FuelShift::factory()->closed('0.00')->create(['fuel_station_id' => $this->station->id, 'closed_at' => '2026-10-12 18:00']);
        FuelShift::factory()->closed('-4.00', 'Kam chiqdi')->create(['fuel_station_id' => $this->station->id, 'closed_at' => '2026-10-13 18:00']);
        FuelShift::factory()->create(['fuel_station_id' => $this->station->id]);

        $this->getJson('/api/fuel/reports/discrepancies?from=2026-10-01&to=2026-10-31')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.discrepancy', '-4.00')
            ->assertJsonPath('0.note', 'Kam chiqdi');
    }

    public function test_reports_are_scoped_to_accessible_stations(): void
    {
        $other = FuelStation::factory()->create(['organization_id' => $this->organization->id]);
        $fueler = User::factory()->withRole('fueler')->create(['organization_id' => $this->organization->id]);
        $this->station->fuelers()->attach($fueler);
        Sanctum::actingAs($fueler);

        $this->getJson('/api/fuel/reports/turnover')->assertOk()->assertJsonCount(1)->assertJsonPath('0.station_id', $this->station->id);
        $this->assertNotSame($other->id, $this->station->id);
    }
}
