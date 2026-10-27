<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fuel_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('tin', 20)->nullable();
            $table->string('phone', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('fuel_station_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fuel_station_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unique(['fuel_station_id', 'user_id']);
        });

        Schema::create('fuel_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fuel_station_id')->constrained();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->foreignId('opened_by')->constrained('users');
            $table->timestamp('opened_at');
            $table->decimal('opening_balance', 10, 2);
            $table->decimal('opening_measured', 10, 2);
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('closing_balance', 10, 2)->nullable();
            $table->decimal('closing_measured', 10, 2)->nullable();
            $table->decimal('discrepancy', 10, 2)->nullable();
            $table->text('discrepancy_note')->nullable();
            $table->timestamps();

            $table->index(['fuel_station_id', 'status']);
        });

        Schema::table('fuel_transactions', function (Blueprint $table) {
            $table->string('kind', 16)->default('adjustment')->after('fuel_station_id');
            $table->foreignId('fuel_shift_id')->nullable()->after('kind')->constrained()->nullOnDelete();
            $table->foreignId('fuel_supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_number')->nullable();
            $table->date('document_date')->nullable();
            $table->decimal('document_amount', 10, 2)->nullable();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('odometer')->nullable();
            $table->unsignedInteger('previous_odometer')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason')->nullable();

            $table->index(['fuel_station_id', 'created_at']);
            $table->index(['vehicle_id', 'kind']);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->decimal('tank_capacity', 8, 2)->nullable()->after('fuel_type');
            $table->unsignedInteger('odometer')->nullable()->after('tank_capacity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['tank_capacity', 'odometer']);
        });

        // MySQL silently drops a foreign key's own index once a composite index
        // starting with the same column can serve it, so give it back first.
        foreach (['fuel_station_id', 'vehicle_id'] as $column) {
            if (! Schema::hasIndex('fuel_transactions', "fuel_transactions_{$column}_foreign")) {
                Schema::table('fuel_transactions', fn (Blueprint $table) => $table->index($column, "fuel_transactions_{$column}_foreign"));
            }
        }

        Schema::table('fuel_transactions', function (Blueprint $table) {
            $table->dropIndex(['fuel_station_id', 'created_at']);
            $table->dropIndex(['vehicle_id', 'kind']);
            $table->dropConstrainedForeignId('fuel_shift_id');
            $table->dropConstrainedForeignId('fuel_supplier_id');
            $table->dropConstrainedForeignId('driver_id');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['kind', 'document_number', 'document_date', 'document_amount', 'odometer', 'previous_odometer', 'cancelled_at', 'cancel_reason']);
        });

        Schema::dropIfExists('fuel_shifts');
        Schema::dropIfExists('fuel_station_user');
        Schema::dropIfExists('fuel_suppliers');
    }
};
