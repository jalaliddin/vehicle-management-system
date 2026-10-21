<?php

use App\Support\Hr\DriverEmployeeBackfill;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Give every existing driver an employee record.
     */
    public function up(): void
    {
        DriverEmployeeBackfill::run();
    }

    /**
     * The created employees are dropped with their table by the previous migration's rollback.
     */
    public function down(): void
    {
        //
    }
};
