<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->string('state_number')->unique();
            $table->string('model');
            $table->year('manufacture_year');
            $table->string('chassis_number')->nullable();
            $table->string('body_type')->nullable();
            $table->string('tech_passport_series')->nullable();
            $table->string('tech_passport_number')->nullable();
            $table->string('engine_number')->nullable();
            $table->decimal('empty_weight', 8, 2)->nullable();
            $table->decimal('full_weight', 8, 2)->nullable();
            $table->string('color')->nullable();
            $table->integer('engine_power')->nullable();
            $table->integer('seat_count')->nullable();
            $table->string('vehicle_type')->default('car');
            $table->decimal('fuel_norm', 8, 2)->nullable();
            $table->decimal('fuel_norm_ac', 8, 2)->nullable();
            $table->string('fuel_type')->default('benzin');
            $table->enum('status', ['active', 'broken', 'maintenance', 'inactive'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
