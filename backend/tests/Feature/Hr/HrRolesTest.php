<?php

namespace Tests\Feature\Hr;

use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class HrRolesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_hr_and_ohs_roles_are_seeded_with_their_permissions(): void
    {
        $this->assertEqualsCanonicalizing(
            ['employees.view', 'employees.manage', 'org_structure.manage', 'ohs.view', 'drivers.view'],
            Role::where('slug', 'hr')->firstOrFail()->permissionKeys(),
        );

        $this->assertEqualsCanonicalizing(
            ['employees.view', 'ohs.view', 'ohs.medical.view', 'ohs.medical.manage', 'ohs.briefings.manage', 'ohs.ppe.manage'],
            Role::where('slug', 'ohs')->firstOrFail()->permissionKeys(),
        );
    }

    public function test_permission_catalog_has_hr_and_ohs_groups(): void
    {
        $groups = collect(Permission::grouped())->keyBy('key');

        $this->assertSame('Kadrlar', $groups['hr']['label']);
        $this->assertCount(3, $groups['hr']['permissions']);
        $this->assertSame('Mehnat muhofazasi', $groups['ohs']['label']);
        $this->assertCount(5, $groups['ohs']['permissions']);
        $this->assertContains('audit.view', array_column($groups['admin']['permissions'], 'key'));
    }

    public function test_hr_config_defaults(): void
    {
        $this->assertSame(30, config('hr.alert_due_soon_days'));
        $this->assertSame(21, config('hr.annual_leave_days'));
        $this->assertSame(30, config('hr.backup.keep_daily'));
        $this->assertSame(12, config('hr.backup.keep_monthly'));
        $this->assertArrayHasKey('own_wish', config('hr.dismissal_reasons'));
    }
}
