<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * System roles for the HR and occupational safety module. Kept as literals
     * so this migration does not change when the Permission enum does.
     *
     * @var array<string, array{name: string, description: string, permissions: list<string>}>
     */
    private const ROLES = [
        'hr' => [
            'name' => "Kadrlar bo'limi",
            'description' => "Xodimlar bazasi, kadrlar buyruqlari, ta'tillar va tuzilma.",
            'permissions' => ['employees.view', 'employees.manage', 'org_structure.manage', 'ohs.view', 'drivers.view'],
        ],
        'ohs' => [
            'name' => 'Mehnat muhofazasi',
            'description' => "Tibbiy ko'riklar, yo'riqnomalar, maxsus kiyim va SHHV.",
            'permissions' => ['employees.view', 'ohs.view', 'ohs.medical.view', 'ohs.medical.manage', 'ohs.briefings.manage', 'ohs.ppe.manage'],
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        foreach (self::ROLES as $slug => $role) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => $role['name'],
                'slug' => $slug,
                'description' => $role['description'],
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('role_permission')->insert(array_map(
                fn (string $permission) => ['role_id' => $roleId, 'permission' => $permission],
                $role['permissions'],
            ));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')->whereIn('slug', array_keys(self::ROLES))->delete();
    }
};
