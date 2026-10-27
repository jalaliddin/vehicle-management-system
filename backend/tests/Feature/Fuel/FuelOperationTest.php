<?php

namespace Tests\Feature\Fuel;

use App\Models\Driver;
use App\Models\FuelStation;
use App\Models\FuelSupplier;
use App\Models\FuelTransaction;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Waybill;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FuelOperationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Organization $organization;

    private FuelStation $station;

    private Vehicle $vehicle;

    private User $fueler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->station = FuelStation::factory()->create([
            'organization_id' => $this->organization->id, 'fuel_type' => 'benzin', 'capacity' => 10000, 'current_balance' => 5000,
        ]);
        $this->vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization->id, 'fuel_type' => 'benzin', 'status' => 'active',
            'tank_capacity' => 60, 'odometer' => 15000, 'state_number' => '90A123BC',
        ]);
        $this->fueler = User::factory()->withRole('fueler')->create(['organization_id' => $this->organization->id]);
        $this->station->fuelers()->attach($this->fueler);
        Sanctum::actingAs($this->fueler);
    }

    private function openShift(): int
    {
        return $this->postJson("/api/fuel/stations/{$this->station->id}/shifts", ['opening_measured' => $this->station->fresh()->current_balance])
            ->assertCreated()->json('id');
    }

    private function refuel(array $data = []): TestResponse
    {
        return $this->postJson("/api/fuel/stations/{$this->station->id}/refuels", array_merge([
            'vehicle_id' => $this->vehicle->id, 'odometer' => 15250, 'amount' => 40,
        ], $data));
    }

    private function receipt(array $data = []): TestResponse
    {
        return $this->postJson("/api/fuel/stations/{$this->station->id}/receipts", array_merge([
            'fuel_supplier_id' => FuelSupplier::factory()->create()->id, 'document_number' => 'YuX-15',
            'document_date' => now()->toDateString(), 'document_amount' => 1000, 'amount' => 995, 'price_per_liter' => 11500,
        ], $data));
    }

    public function test_receipt_requires_open_shift(): void
    {
        $this->receipt()->assertUnprocessable()->assertJsonPath('message', 'Smena ochilmagan');
        $this->refuel()->assertUnprocessable()->assertJsonPath('message', 'Smena ochilmagan');
    }

    public function test_receipt_adds_to_balance_and_records_document(): void
    {
        $shiftId = $this->openShift();

        $this->receipt()->assertCreated()
            ->assertJsonPath('kind', 'receipt')
            ->assertJsonPath('document_amount', '1000.00')
            ->assertJsonPath('fuel_shift_id', $shiftId);

        $this->assertSame('5995.00', $this->station->fresh()->current_balance);
    }

    public function test_receipt_over_capacity_is_rejected(): void
    {
        $this->openShift();

        $this->receipt(['amount' => 6000, 'document_amount' => 6000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount' => "Sig'imdan oshib ketadi"]);
    }

    public function test_refuel_links_open_waybill_and_driver(): void
    {
        $this->openShift();
        $waybill = Waybill::factory()->create(['organization_id' => $this->organization->id, 'vehicle_id' => $this->vehicle->id, 'status' => 'in_progress']);

        $this->refuel()->assertCreated()
            ->assertJsonPath('waybill_id', $waybill->id)
            ->assertJsonPath('driver_id', $waybill->driver_id)
            ->assertJsonPath('odometer', 15250);

        $this->assertSame('4960.00', $this->station->fresh()->current_balance);
    }

    public function test_refuel_without_waybill_is_allowed(): void
    {
        $this->openShift();

        $this->refuel()->assertCreated()->assertJsonPath('waybill_id', null);
    }

    public function test_refuel_takes_vehicle_driver_when_no_waybill(): void
    {
        $this->openShift();
        $driver = Driver::factory()->create(['organization_id' => $this->organization->id, 'vehicle_id' => $this->vehicle->id]);

        $this->refuel()->assertCreated()->assertJsonPath('driver_id', $driver->id);
    }

    public function test_refuel_rejects_wrong_fuel_type(): void
    {
        $this->openShift();
        $this->vehicle->update(['fuel_type' => 'dizel']);

        $this->refuel()->assertUnprocessable()->assertJsonValidationErrors(['vehicle_id' => "Yoqilg'i turi mos emas"]);
    }

    public function test_refuel_rejects_more_than_station_balance(): void
    {
        $this->station->update(['current_balance' => 30]);
        $this->openShift();

        $this->refuel()->assertUnprocessable()->assertJsonValidationErrors(['amount' => "Shahobchada yetarli yoqilg'i yo'q"]);
    }

    public function test_refuel_rejects_more_than_tank(): void
    {
        $this->openShift();

        $this->refuel(['amount' => 70])->assertUnprocessable()->assertJsonValidationErrors(['amount' => "Bak hajmidan ko'p"]);
    }

    public function test_refuel_rejects_lower_odometer(): void
    {
        $this->openShift();

        $this->refuel(['odometer' => 14000])->assertUnprocessable()
            ->assertJsonValidationErrors(['odometer' => "Spidometr ko'rsatkichi oldingisidan kam"]);
    }

    public function test_refuel_rejects_foreign_or_inactive_vehicle(): void
    {
        $this->openShift();
        $foreign = Vehicle::factory()->create(['fuel_type' => 'benzin']);
        $inactive = Vehicle::factory()->create(['organization_id' => $this->organization->id, 'fuel_type' => 'benzin', 'status' => 'inactive']);

        $this->refuel(['vehicle_id' => $foreign->id])->assertUnprocessable()->assertJsonValidationErrors('vehicle_id');
        $this->refuel(['vehicle_id' => $inactive->id])->assertUnprocessable()->assertJsonValidationErrors('vehicle_id');
    }

    public function test_refuel_raises_odometer_and_remembers_previous(): void
    {
        $this->openShift();

        $this->refuel()->assertCreated()->assertJsonPath('previous_odometer', 15000);

        $this->assertSame(15250, $this->vehicle->fresh()->odometer);
    }

    public function test_first_refuel_sets_missing_odometer(): void
    {
        $this->openShift();
        $this->vehicle->update(['odometer' => null]);

        $this->refuel(['odometer' => 120])->assertCreated();

        $this->assertSame(120, $this->vehicle->fresh()->odometer);
    }

    public function test_decimal_amounts_do_not_drift(): void
    {
        $this->station->update(['current_balance' => 100]);
        $this->vehicle->update(['tank_capacity' => null]);
        $this->openShift();

        foreach ([15100, 15200, 15300] as $odometer) {
            $this->refuel(['amount' => '33.33', 'odometer' => $odometer])->assertCreated();
        }

        $this->assertSame('0.01', $this->station->fresh()->current_balance);
    }

    public function test_cancel_reverses_balance_and_odometer(): void
    {
        $this->openShift();
        $id = $this->refuel()->json('id');

        $this->postJson("/api/fuel/transactions/{$id}/cancel", ['reason' => 'Xato raqam'])->assertOk();

        $this->assertSame('5000.00', $this->station->fresh()->current_balance);
        $this->assertSame(15000, $this->vehicle->fresh()->odometer);
        $this->assertNotNull(FuelTransaction::find($id)->cancelled_at);
    }

    public function test_cancel_older_refuel_keeps_newer_odometer(): void
    {
        $this->openShift();
        $older = $this->refuel(['odometer' => 15250])->json('id');
        $this->refuel(['odometer' => 15500, 'amount' => 20])->assertCreated();

        $this->postJson("/api/fuel/transactions/{$older}/cancel", ['reason' => 'Xato'])->assertOk();

        $this->assertSame(15500, $this->vehicle->fresh()->odometer);
    }

    public function test_cancel_after_shift_close_is_rejected(): void
    {
        $shiftId = $this->openShift();
        $id = $this->refuel()->json('id');
        $this->postJson("/api/fuel/shifts/{$shiftId}/close", ['closing_measured' => 4960])->assertOk();

        $this->postJson("/api/fuel/transactions/{$id}/cancel", ['reason' => 'Kech'])
            ->assertUnprocessable()
            ->assertJsonPath('message', "Smena yopilgan, bekor qilib bo'lmaydi");
    }

    public function test_cancelling_a_receipt_cannot_make_balance_negative(): void
    {
        $this->station->update(['current_balance' => 0]);
        $this->vehicle->update(['tank_capacity' => null]);
        $this->openShift();
        $receipt = $this->receipt(['amount' => 100, 'document_amount' => 100])->json('id');
        $this->refuel(['amount' => 80])->assertCreated();

        $this->postJson("/api/fuel/transactions/{$receipt}/cancel", ['reason' => 'Xato'])
            ->assertUnprocessable()
            ->assertJsonPath('message', "Shahobchada yetarli yoqilg'i yo'q");
    }

    public function test_vehicle_lookup_reports_fuel_type_match_and_waybill(): void
    {
        $waybill = Waybill::factory()->create(['organization_id' => $this->organization->id, 'vehicle_id' => $this->vehicle->id, 'status' => 'approved']);
        Vehicle::factory()->create(['organization_id' => $this->organization->id, 'fuel_type' => 'dizel', 'state_number' => '90B999BC']);

        $this->getJson("/api/fuel/stations/{$this->station->id}/vehicles?search=90")
            ->assertOk()
            ->assertJsonCount(2);

        $this->getJson("/api/fuel/stations/{$this->station->id}/vehicles?search=A123")
            ->assertOk()
            ->assertJsonPath('0.fuel_type_matches', true)
            ->assertJsonPath('0.odometer', 15000)
            ->assertJsonPath('0.waybill.id', $waybill->id);

        $this->getJson("/api/fuel/stations/{$this->station->id}/vehicles?search=B999")
            ->assertOk()
            ->assertJsonPath('0.fuel_type_matches', false);
    }

    public function test_old_transaction_endpoint_is_gone(): void
    {
        $status = $this->postJson("/api/fuel/stations/{$this->station->id}/transaction", ['type' => 'out', 'amount' => 10])->status();

        $this->assertContains($status, [404, 405]);
    }

    public function test_vehicle_form_accepts_tank_and_odometer(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->putJson("/api/vehicles/{$this->vehicle->id}", ['tank_capacity' => 80, 'odometer' => 16000])->assertOk();

        $this->assertSame(['80.00', 16000], [$this->vehicle->fresh()->tank_capacity, $this->vehicle->fresh()->odometer]);
    }

    public function test_transactions_list_filters_by_kind(): void
    {
        $this->openShift();
        $this->receipt()->assertCreated();
        $this->refuel()->assertCreated();

        $this->getJson('/api/fuel/transactions?kind=refuel')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.vehicle.state_number', '90A123BC');
    }
}
