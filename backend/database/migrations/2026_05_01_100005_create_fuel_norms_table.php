<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_norms', function (Blueprint $table) {
            $table->id();
            $table->string('route_name');
            $table->string('route_number')->nullable();
            $table->string('vehicle_model');
            $table->decimal('norm_without_ac', 8, 2);
            $table->decimal('norm_with_ac', 8, 2)->nullable();
            $table->decimal('heating_norm', 8, 2)->nullable();
            $table->string('fuel_type')->default('benzin');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_norms');
    }
};
