<?php

namespace Tests\Feature\Fuel;

use App\Models\FuelStation;
use App\Models\FuelTransaction;
use App\Models\Role;
use App\Support\Fuel\OpeningBalances;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FuelSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_fueler_role_is_seeded(): void
    {
        $this->assertEqualsCanonicalizing(
            ['fuel.view', 'fuel.transactions.create'],
            Role::where('slug', 'fueler')->firstOrFail()->permissionKeys(),
        );
    }

    public function test_opening_balance_rows_match_station_balances(): void
    {
        $full = FuelStation::factory()->create(['current_balance' => 3200]);
        FuelStation::factory()->create(['current_balance' => 0]);

        $this->assertSame(1, OpeningBalances::record());

        $row = FuelTransaction::sole();
        $this->assertSame([$full->id, 'adjustment', 'in', '3200.00', FuelTransaction::OPENING_DOCUMENT], [
            $row->fuel_station_id, $row->kind, $row->type, $row->amount, $row->document_number,
        ]);
        $this->assertSame(0, OpeningBalances::record());
    }

    public function test_station_unit_comes_from_config(): void
    {
        $this->assertSame('m³', FuelStation::factory()->make(['fuel_type' => 'gaz'])->unit());
        $this->assertSame('L', FuelStation::factory()->make(['fuel_type' => 'dizel'])->unit());
    }
}
