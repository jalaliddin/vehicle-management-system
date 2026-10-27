<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Kept as literals so this migration does not change when the Permission enum does.
     */
    private const PERMISSIONS = ['fuel.view', 'fuel.transactions.create'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => "Yoqilg'i quyuvchi",
            'slug' => 'fueler',
            'description' => "Smenani ochadi va yopadi, yoqilg'i qabul qiladi va quyadi.",
            'is_system' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('role_permission')->insert(array_map(
            fn (string $permission) => ['role_id' => $roleId, 'permission' => $permission],
            self::PERMISSIONS,
        ));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')->where('slug', 'fueler')->delete();
    }
};
