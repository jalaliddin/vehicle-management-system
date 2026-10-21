<?php

namespace Tests\Feature\Hr;

use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\Concerns\Auditable;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->refreshDatabase();

        Schema::create('audited_things', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_creating_updating_deleting_an_auditable_model_writes_entries(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $thing = AuditedThing::create(['name' => 'A', 'organization_id' => 5, 'employee_id' => 7]);
        $thing->update(['name' => 'B']);
        $thing->delete();

        $entries = AuditLog::orderBy('id')->get();

        $this->assertSame(['created', 'updated', 'deleted'], $entries->pluck('event')->all());
        $this->assertSame(['name' => 'A'], $entries[1]->old_values);
        $this->assertSame(['name' => 'B'], $entries[1]->new_values);
        $this->assertSame('A', $entries[0]->new_values['name']);
        $this->assertArrayNotHasKey('created_at', $entries[0]->new_values);
        $this->assertSame('B', $entries[2]->old_values['name']);
        $this->assertSame($user->id, $entries[0]->user_id);
        $this->assertSame(5, $entries[0]->organization_id);
        $this->assertSame(7, $entries[0]->employee_id);
        $this->assertSame(AuditedThing::class, $entries[0]->auditable_type);
        $this->assertSame($thing->id, $entries[0]->auditable_id);
    }

    public function test_an_update_without_changes_writes_nothing(): void
    {
        $thing = AuditedThing::create(['name' => 'A']);
        $thing->update(['name' => 'A']);

        $this->assertSame(1, AuditLog::count());
    }

    public function test_audit_entries_cannot_be_changed(): void
    {
        $entry = AuditLog::record(AuditedThing::create(['name' => 'A']), 'viewed');

        $this->expectException(LogicException::class);
        $entry->update(['event' => 'created']);
    }

    public function test_audit_entries_cannot_be_deleted(): void
    {
        $entry = AuditLog::record(AuditedThing::create(['name' => 'A']), 'viewed');

        $this->expectException(LogicException::class);
        $entry->delete();
    }

    public function test_audit_log_endpoint_requires_audit_view(): void
    {
        Sanctum::actingAs(User::factory()->withRole('viewer')->create());

        $this->getJson('/api/audit-logs')->assertForbidden();
    }

    public function test_audit_log_endpoint_is_scoped_to_users_organization(): void
    {
        $own = Organization::factory()->create();
        $other = Organization::factory()->create();
        AuditedThing::create(['name' => 'mine', 'organization_id' => $own->id]);
        AuditedThing::create(['name' => 'theirs', 'organization_id' => $other->id]);

        Sanctum::actingAs(User::factory()->withPermissions(Permission::AuditView)->create(['organization_id' => $own->id]));

        $this->getJson('/api/audit-logs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.new_values.name', 'mine');
    }

    public function test_audit_log_endpoint_filters_by_employee_id(): void
    {
        AuditedThing::create(['name' => 'first', 'employee_id' => 1]);
        AuditedThing::create(['name' => 'second', 'employee_id' => 2]);

        Sanctum::actingAs(User::factory()->withPermissions(Permission::AuditView, Permission::AllOrganizations)->create());

        $this->getJson('/api/audit-logs?employee_id=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.new_values.name', 'second')
            ->assertJsonPath('total', 1);
    }
}

class AuditedThing extends Model
{
    use Auditable;

    protected $fillable = ['name', 'organization_id', 'employee_id'];
}
