<?php

namespace Tests\Feature\Fuel;

use App\Models\FuelStation;
use App\Models\FuelTransaction;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StationAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Organization $organization;

    private FuelStation $mine;

    private FuelStation $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->mine = FuelStation::factory()->create(['organization_id' => $this->organization->id]);
        $this->other = FuelStation::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function fueler(): User
    {
        $user = User::factory()->withRole('fueler')->create(['organization_id' => $this->organization->id]);
        $this->mine->fuelers()->attach($user);

        return $user;
    }

    public function test_assigned_fueler_sees_only_assigned_stations(): void
    {
        Sanctum::actingAs($this->fueler());

        $this->getJson('/api/fuel/monitoring')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $this->mine->id);
        $this->getJson('/api/fuel/stations')->assertOk()->assertJsonCount(1);
    }

    public function test_unassigned_user_keeps_organization_scope(): void
    {
        FuelStation::factory()->create();
        Sanctum::actingAs(User::factory()->withRole('dispatcher')->create(['organization_id' => $this->organization->id]));

        $this->getJson('/api/fuel/stations')->assertOk()->assertJsonCount(2);
    }

    public function test_manager_assigns_fuelers(): void
    {
        $fueler = User::factory()->withRole('fueler')->create(['organization_id' => $this->organization->id]);
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->putJson("/api/fuel/stations/{$this->mine->id}/fuelers", ['user_ids' => [$fueler->id]])->assertOk();

        $this->assertSame([$fueler->id], $this->mine->fuelers()->pluck('users.id')->all());
        $this->getJson('/api/fuel/stations')->assertOk()->assertJsonPath('0.fuelers.0.id', $fueler->id);
    }

    public function test_fueler_from_another_organization_cannot_be_assigned(): void
    {
        $stranger = User::factory()->withRole('fueler')->create(['organization_id' => Organization::factory()->create()->id]);
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->putJson("/api/fuel/stations/{$this->mine->id}/fuelers", ['user_ids' => [$stranger->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_ids');
    }

    public function test_user_without_operate_permission_cannot_be_assigned(): void
    {
        $viewer = User::factory()->withRole('viewer')->create(['organization_id' => $this->organization->id]);
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->putJson("/api/fuel/stations/{$this->mine->id}/fuelers", ['user_ids' => [$viewer->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_ids');
    }

    public function test_transactions_list_is_limited_to_accessible_stations(): void
    {
        FuelTransaction::factory()->create(['fuel_station_id' => $this->mine->id]);
        FuelTransaction::factory()->create(['fuel_station_id' => $this->other->id]);
        Sanctum::actingAs($this->fueler());

        $this->getJson('/api/fuel/transactions')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_fueler_cannot_edit_station(): void
    {
        Sanctum::actingAs($this->fueler());

        $this->putJson("/api/fuel/stations/{$this->mine->id}", ['name' => 'X'])->assertForbidden();
    }
}
