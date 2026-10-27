<?php

namespace Tests\Feature\Fuel;

use App\Models\FuelSupplier;
use App\Models\FuelTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FuelSupplierTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_supplier_crud_and_in_use_delete_guard(): void
    {
        Sanctum::actingAs(User::factory()->withRole('hq_dispatcher')->create());

        $id = $this->postJson('/api/fuel/suppliers', ['name' => "O'zbekneftgaz", 'tin' => '200000001'])->assertCreated()->json('id');
        $this->putJson("/api/fuel/suppliers/{$id}", ['name' => 'Yangi nom'])->assertOk()->assertJsonPath('name', 'Yangi nom');
        $this->getJson('/api/fuel/suppliers')->assertOk()->assertJsonCount(1);

        FuelTransaction::factory()->receipt()->create(['fuel_supplier_id' => $id]);
        $this->deleteJson("/api/fuel/suppliers/{$id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', "Ma'lumot ishlatilmoqda, o'chirib bo'lmaydi");

        $unused = FuelSupplier::factory()->create();
        $this->deleteJson("/api/fuel/suppliers/{$unused->id}")->assertNoContent();
    }

    public function test_fueler_cannot_manage_suppliers(): void
    {
        Sanctum::actingAs(User::factory()->withRole('fueler')->create());

        $this->getJson('/api/fuel/suppliers')->assertOk();
        $this->postJson('/api/fuel/suppliers', ['name' => 'X'])->assertForbidden();
    }
}
