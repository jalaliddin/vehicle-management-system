<?php

namespace Tests\Feature\Hr;

use App\Models\Department;
use App\Models\Organization;
use App\Models\Position;
use App\Models\PpeItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrgStructureTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
    }

    private function actingAsHr(): User
    {
        $user = User::factory()->withRole('hr')->create(['organization_id' => $this->organization->id]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_hr_user_creates_department_in_own_organization(): void
    {
        $this->actingAsHr();

        $this->postJson('/api/departments', ['organization_id' => $this->organization->id, 'name' => 'Transport sexi'])
            ->assertCreated()
            ->assertJsonPath('name', 'Transport sexi');

        $this->getJson('/api/departments?organization_id='.$this->organization->id)
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_hr_user_cannot_create_department_in_other_organization(): void
    {
        $this->actingAsHr();
        $other = Organization::factory()->create();

        $this->postJson('/api/departments', ['organization_id' => $other->id, 'name' => 'Sex'])->assertForbidden();
    }

    public function test_department_list_is_scoped_to_users_organization(): void
    {
        $this->actingAsHr();
        Department::factory()->create(['organization_id' => $this->organization->id]);
        Department::factory()->create();

        $this->getJson('/api/departments')->assertOk()->assertJsonCount(1);
    }

    public function test_department_name_unique_among_siblings(): void
    {
        $this->actingAsHr();
        $parent = Department::factory()->create(['organization_id' => $this->organization->id]);
        Department::factory()->create(['organization_id' => $this->organization->id, 'parent_id' => $parent->id, 'name' => 'Garaj']);

        $this->postJson('/api/departments', [
            'organization_id' => $this->organization->id, 'parent_id' => $parent->id, 'name' => 'Garaj',
        ])->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->postJson('/api/departments', ['organization_id' => $this->organization->id, 'name' => 'Garaj'])
            ->assertCreated();
    }

    public function test_parent_department_must_belong_to_the_same_organization(): void
    {
        $this->actingAsHr();
        $foreign = Department::factory()->create();

        $this->postJson('/api/departments', [
            'organization_id' => $this->organization->id, 'parent_id' => $foreign->id, 'name' => 'Sex',
        ])->assertUnprocessable()->assertJsonValidationErrors('parent_id');
    }

    public function test_department_with_children_cannot_be_deleted(): void
    {
        $this->actingAsHr();
        $parent = Department::factory()->create(['organization_id' => $this->organization->id]);
        Department::factory()->create(['organization_id' => $this->organization->id, 'parent_id' => $parent->id]);

        $this->deleteJson("/api/departments/{$parent->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', "Ichki bo'limlar mavjud");

        $this->assertModelExists($parent);
    }

    public function test_position_stores_periodicities(): void
    {
        $this->actingAsHr();

        $this->postJson('/api/positions', ['name' => 'Haydovchi', 'medical_exam_months' => 12, 'briefing_months' => 6])
            ->assertCreated();

        $this->assertDatabaseHas('positions', ['name' => 'Haydovchi', 'medical_exam_months' => 12, 'briefing_months' => 6]);
    }

    public function test_ppe_norms_put_replaces_the_set(): void
    {
        $this->actingAsHr();
        $position = Position::factory()->create();
        [$boots, $gloves] = PpeItem::factory()->count(2)->create();

        $this->putJson("/api/positions/{$position->id}/ppe-norms", ['norms' => [
            ['ppe_item_id' => $boots->id, 'quantity' => 1, 'wear_months' => 12],
            ['ppe_item_id' => $gloves->id, 'quantity' => 6, 'wear_months' => 1],
        ]])->assertOk();

        $this->putJson("/api/positions/{$position->id}/ppe-norms", ['norms' => [
            ['ppe_item_id' => $gloves->id, 'quantity' => 12, 'wear_months' => 1],
        ]])->assertOk()->assertJsonCount(1);

        $this->assertDatabaseCount('position_ppe_norms', 1);
        $this->assertDatabaseHas('position_ppe_norms', ['position_id' => $position->id, 'ppe_item_id' => $gloves->id, 'quantity' => 12]);
        $this->getJson("/api/positions/{$position->id}/ppe-norms")->assertOk()->assertJsonPath('0.ppe_item.id', $gloves->id);
    }

    public function test_ppe_item_in_a_norm_cannot_be_deleted(): void
    {
        $position = Position::factory()->create();
        $item = PpeItem::factory()->create();
        $position->ppeNorms()->create(['ppe_item_id' => $item->id, 'quantity' => 1, 'wear_months' => 12]);
        Sanctum::actingAs(User::factory()->withRole('ohs')->create(['organization_id' => $this->organization->id]));

        $this->deleteJson("/api/ppe-items/{$item->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', "Ma'lumot ishlatilmoqda, o'chirib bo'lmaydi");
    }

    public function test_unused_position_can_be_deleted(): void
    {
        $this->actingAsHr();
        $position = Position::factory()->create();

        $this->deleteJson("/api/positions/{$position->id}")->assertNoContent();
        $this->assertModelMissing($position);
    }

    public function test_viewer_cannot_manage_catalogs(): void
    {
        Sanctum::actingAs(User::factory()->withRole('viewer')->create(['organization_id' => $this->organization->id]));

        $this->getJson('/api/positions')->assertForbidden();
        $this->postJson('/api/positions', ['name' => 'X'])->assertForbidden();
        $this->postJson('/api/ppe-items', ['name' => 'X', 'kind' => 'ppe'])->assertForbidden();
        $this->postJson('/api/departments', ['organization_id' => $this->organization->id, 'name' => 'X'])->assertForbidden();
    }

    public function test_hr_user_cannot_manage_ppe_items(): void
    {
        $this->actingAsHr();

        $this->getJson('/api/ppe-items')->assertOk();
        $this->postJson('/api/ppe-items', ['name' => 'Etik', 'kind' => 'footwear'])->assertForbidden();
    }
}
