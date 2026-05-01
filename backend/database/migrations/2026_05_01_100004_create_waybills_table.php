<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waybills', function (Blueprint $table) {
            $table->id();
            $table->string('waybill_number')->unique();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('vehicle_id')->constrained();
            $table->foreignId('driver_id')->constrained();
            $table->string('vehicle_type');
            $table->string('tabel_number')->nullable();
            $table->date('created_date');
            $table->date('valid_until');
            $table->integer('trip_count')->default(1);
            $table->string('destination');
            $table->string('destination_organization')->nullable();
            $table->json('route_coordinates')->nullable();
            $table->decimal('planned_km', 8, 2)->nullable();
            $table->decimal('actual_km', 8, 2)->nullable();
            $table->decimal('fuel_issued', 8, 2)->nullable();
            $table->decimal('fuel_consumed', 8, 2)->nullable();
            $table->decimal('fuel_returned', 8, 2)->nullable();
            $table->boolean('has_ac')->default(false);

            $table->enum('status', [
                'draft',
                'mechanic_check',
                'mechanic_ok',
                'doctor_check',
                'doctor_ok',
                'hq_review',
                'approved',
                'in_progress',
                'completed',
                'cancelled',
            ])->default('draft');

            $table->foreignId('mechanic_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('mechanic_checked_at')->nullable();
            $table->text('mechanic_notes')->nullable();
            $table->boolean('mechanic_passed')->nullable();

            $table->foreignId('doctor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('doctor_checked_at')->nullable();
            $table->text('doctor_notes')->nullable();
            $table->boolean('doctor_passed')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();

            $table->timestamp('departed_at')->nullable();
            $table->timestamp('arrived_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waybills');
    }
};
