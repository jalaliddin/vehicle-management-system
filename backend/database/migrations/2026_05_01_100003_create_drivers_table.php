<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->string('full_name');
            $table->string('license_number')->unique();
            $table->string('license_category');
            $table->foreignId('vehicle_id')->nullable()->constrained()->onDelete('set null');
            $table->integer('work_experience')->default(0);
            $table->string('pinfl', 14)->unique();
            $table->string('phone')->nullable();
            $table->date('license_expiry')->nullable();
            $table->enum('employment_type', ['staff', 'contract', 'part_time'])->default('staff');
            $table->enum('status', ['active', 'inactive', 'sick_leave', 'vacation'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
