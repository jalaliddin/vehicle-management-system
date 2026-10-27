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
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedTinyInteger('medical_exam_months')->nullable();
            $table->unsignedTinyInteger('briefing_months')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ppe_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('kind', ['clothing', 'footwear', 'ppe']);
            $table->string('unit')->default('dona');
            $table->unsignedSmallInteger('default_wear_months')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('position_ppe_norms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ppe_item_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->unsignedSmallInteger('wear_months');
            $table->timestamps();

            $table->unique(['position_id', 'ppe_item_id']);
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('personnel_number')->nullable();
            $table->string('last_name');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->char('pinfl', 14)->unique();
            $table->string('passport', 20)->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('education')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('clothing_size', 10)->nullable();
            $table->string('shoe_size', 10)->nullable();
            $table->enum('employment_type', ['staff', 'contract', 'part_time'])->default('staff');
            $table->enum('status', ['active', 'dismissed'])->default('active');
            $table->date('hire_date')->nullable();
            $table->date('dismissal_date')->nullable();
            $table->unsignedSmallInteger('annual_leave_days');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['last_name', 'first_name']);
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->unique()->after('organization_id')->constrained()->nullOnDelete();
        });

        Schema::create('employment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['hire', 'transfer', 'dismissal']);
            $table->date('effective_date');
            $table->string('order_number');
            $table->date('order_date');
            $table->foreignId('organization_id')->nullable()->constrained();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained();
            $table->string('dismissal_reason')->nullable();
            $table->string('dismissal_article')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'effective_date']);
        });

        Schema::create('employee_absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['annual_leave', 'unpaid_leave', 'study_leave', 'maternity_leave', 'sick_leave', 'business_trip', 'absenteeism', 'other']);
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('days');
            $table->boolean('is_planned')->default(false);
            $table->string('document_number')->nullable();
            $table->date('document_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'start_date']);
        });

        Schema::create('medical_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['preliminary', 'periodic', 'extraordinary']);
            $table->date('exam_date');
            $table->string('institution')->nullable();
            $table->enum('result', ['fit', 'fit_with_restrictions', 'unfit']);
            $table->text('restrictions')->nullable();
            $table->date('next_due_date')->nullable();
            $table->string('document_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'exam_date']);
        });

        Schema::create('safety_briefings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->enum('type', ['introductory', 'primary', 'repeated', 'unscheduled', 'targeted']);
            $table->date('conducted_on');
            $table->string('topic');
            $table->foreignId('instructor_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('instructor_name')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'conducted_on']);
        });

        Schema::create('safety_briefing_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('safety_briefing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('next_due_date')->nullable();

            $table->unique(['safety_briefing_id', 'employee_id'], 'briefing_participant_unique');
        });

        Schema::create('ppe_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ppe_item_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->string('size', 10)->nullable();
            $table->date('issued_on');
            $table->date('due_on')->nullable();
            $table->enum('status', ['in_use', 'replaced', 'returned', 'written_off'])->default('in_use');
            $table->date('closed_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ppe_issues');
        Schema::dropIfExists('safety_briefing_participants');
        Schema::dropIfExists('safety_briefings');
        Schema::dropIfExists('medical_exams');
        Schema::dropIfExists('employee_absences');
        Schema::dropIfExists('employment_events');

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
        });

        Schema::dropIfExists('employees');
        Schema::dropIfExists('position_ppe_norms');
        Schema::dropIfExists('ppe_items');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
    }
};
