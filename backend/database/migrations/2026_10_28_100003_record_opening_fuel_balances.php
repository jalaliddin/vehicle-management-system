<?php

use App\Models\FuelTransaction;
use App\Support\Fuel\OpeningBalances;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Record each station's existing balance as an opening ledger row.
     */
    public function up(): void
    {
        OpeningBalances::record();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        FuelTransaction::where('document_number', FuelTransaction::OPENING_DOCUMENT)->delete();
    }
};
