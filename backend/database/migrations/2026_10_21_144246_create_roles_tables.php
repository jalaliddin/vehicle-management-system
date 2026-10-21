<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The roles that replace the old users.role enum, with their default
     * permissions. Kept as literals so this migration does not change when
     * the Permission enum does. Super Admin gets every permission in code.
     *
     * @var array<string, array{name: string, description: string, permissions: list<string>}>
     */
    private const SYSTEM_ROLES = [
        'superadmin' => [
            'name' => 'Super Admin',
            'description' => "Barcha huquqlarga ega, o'zgartirib bo'lmaydi.",
            'permissions' => [],
        ],
        'hq_dispatcher' => [
            'name' => 'Markaziy dispetcher',
            'description' => 'Barcha tashkilotlar bo\'yicha transport va yo\'l varaqalarini boshqaradi.',
            'permissions' => [
                'data.all_organizations',
                'vehicles.view', 'vehicles.create', 'vehicles.update', 'vehicles.delete',
                'drivers.view', 'drivers.create', 'drivers.update', 'drivers.delete',
                'waybills.view', 'waybills.create', 'waybills.update', 'waybills.delete',
                'waybills.submit', 'waybills.approve', 'waybills.depart', 'waybills.complete',
                'fuel.view', 'fuel.stations.manage', 'fuel.transactions.create',
                'fuel_norms.view', 'fuel_norms.manage',
                'reports.view',
                'organizations.manage',
            ],
        ],
        'dispatcher' => [
            'name' => 'Dispetcher',
            'description' => "O'z tashkilotida yo'l varaqalarini yaratadi va yuritadi.",
            'permissions' => [
                'vehicles.view', 'vehicles.create', 'vehicles.update',
                'drivers.view', 'drivers.create', 'drivers.update',
                'waybills.view', 'waybills.create', 'waybills.update', 'waybills.delete',
                'waybills.submit', 'waybills.depart', 'waybills.complete',
                'fuel.view', 'fuel.transactions.create',
                'reports.view',
            ],
        ],
        'mechanic' => [
            'name' => 'Mexanik',
            'description' => "Avtomobillarni yo'lga chiqishdan oldin tekshiradi.",
            'permissions' => [
                'vehicles.view', 'drivers.view', 'waybills.view', 'waybills.mechanic_check',
            ],
        ],
        'doctor' => [
            'name' => 'Shifokor',
            'description' => "Haydovchilarni yo'lga chiqishdan oldin tibbiy ko'rikdan o'tkazadi.",
            'permissions' => [
                'drivers.view', 'waybills.view', 'waybills.doctor_check',
            ],
        ],
        'viewer' => [
            'name' => "Ko'ruvchi",
            'description' => "O'z tashkiloti ma'lumotlarini faqat ko'radi.",
            'permissions' => [
                'vehicles.view', 'drivers.view', 'waybills.view',
                'fuel.view', 'fuel_norms.view', 'reports.view',
            ],
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('role_permission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('permission');
            $table->unique(['role_id', 'permission']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('organization_id')->constrained()->restrictOnDelete();
        });

        $now = now();

        foreach (self::SYSTEM_ROLES as $slug => $role) {
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

            DB::table('users')->where('role', $slug)->update(['role_id' => $roleId]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', array_keys(self::SYSTEM_ROLES))->default('dispatcher')->after('organization_id');
        });

        foreach (array_keys(self::SYSTEM_ROLES) as $slug) {
            $roleId = DB::table('roles')->where('slug', $slug)->value('id');
            DB::table('users')->where('role_id', $roleId)->update(['role' => $slug]);
        }

        DB::table('users')
            ->whereNotIn('role_id', DB::table('roles')->where('is_system', true)->select('id'))
            ->update(['role' => 'viewer']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('roles');
    }
};
