<?php

namespace Tests\Feature\Hr;

use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\Concerns\Auditable;
use App\Models\Employee;
use App\Models\MedicalExam;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Waybill;
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

        $entries = AuditLog::where('auditable_type', AuditedThing::class)->orderBy('id')->get();

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

        $this->assertSame(1, AuditLog::where('auditable_type', AuditedThing::class)->count());
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

    public function test_existing_models_are_audited(): void
    {
        $waybill = Waybill::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $waybill->update(['destination' => 'Xiva']);
        $vehicle->update(['status' => 'maintenance']);

        $this->assertTrue(AuditLog::where('auditable_type', Waybill::class)->where('event', 'updated')
            ->where('auditable_id', $waybill->id)->exists());
        $this->assertSame(['status' => 'maintenance'], AuditLog::where('auditable_type', Vehicle::class)
            ->where('event', 'updated')->latest('id')->first()->new_values);
    }

    public function test_user_password_is_never_logged(): void
    {
        $user = User::factory()->create();
        $user->update(['password' => 'new-secret', 'name' => 'Yangi ism']);

        $entry = AuditLog::where('auditable_type', User::class)->where('event', 'updated')->latest('id')->firstOrFail();
        $this->assertSame(['name' => 'Yangi ism'], $entry->new_values);
    }

    public function test_role_permission_changes_are_audited(): void
    {
        $role = Role::factory()->withPermissions(Permission::VehiclesView)->create();

        $role->syncPermissions(['vehicles.view', 'drivers.view']);

        $entry = AuditLog::where('auditable_type', Role::class)->where('event', 'permissions_synced')->latest('id')->firstOrFail();
        $this->assertSame(['vehicles.view'], $entry->old_values['permissions']);
        $this->assertSame(['drivers.view', 'vehicles.view'], $entry->new_values['permissions']);
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

        $entries = collect($this->getJson('/api/audit-logs')->assertOk()->json('data'));

        $this->assertSame([$own->id], $entries->pluck('organization_id')->unique()->values()->all());
        $this->assertContains('mine', $entries->pluck('new_values.name'));
        $this->assertNotContains('theirs', $entries->pluck('new_values.name'));
    }

    public function test_scoped_user_without_organization_sees_no_entries(): void
    {
        AuditedThing::create(['name' => 'global', 'organization_id' => null]);
        Sanctum::actingAs(User::factory()->withPermissions(Permission::AuditView)->create(['organization_id' => null]));

        $this->getJson('/api/audit-logs')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_employee_records_are_logged_under_the_employees_organization(): void
    {
        $organization = Organization::factory()->create();
        $exam = MedicalExam::factory()->for(Employee::factory()->create(['organization_id' => $organization->id]))->create();

        $this->assertSame($organization->id, AuditLog::where('auditable_type', MedicalExam::class)
            ->where('auditable_id', $exam->id)->value('organization_id'));
    }

    public function test_medical_details_in_the_log_need_medical_view(): void
    {
        $organization = Organization::factory()->create();
        $employee = Employee::factory()->create(['organization_id' => $organization->id]);
        MedicalExam::factory()->for($employee)->create(['restrictions' => 'Tungi ish taqiqlangan']);

        Sanctum::actingAs(User::factory()->withPermissions(Permission::AuditView, Permission::EmployeesView)->create(['organization_id' => $organization->id]));
        $history = json_encode($this->getJson("/api/employees/{$employee->id}/history")->assertOk()->json('data'));
        $log = json_encode($this->getJson('/api/audit-logs')->assertOk()->json('data'));
        $this->assertStringNotContainsString('Tungi ish', $history);
        $this->assertStringNotContainsString('Tungi ish', $log);

        Sanctum::actingAs(User::factory()->withPermissions(Permission::AuditView, Permission::EmployeesView, Permission::OhsMedicalView)->create(['organization_id' => $organization->id]));
        $this->assertStringContainsString('Tungi ish', json_encode($this->getJson("/api/employees/{$employee->id}/history")->json('data')));
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
