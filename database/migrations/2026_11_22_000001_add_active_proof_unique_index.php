<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add partial unique index on PostgreSQL to prevent race conditions during proof verification (F-06)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                CREATE UNIQUE INDEX IF NOT EXISTS payment_proofs_active_invoice_unique 
                ON payment_proofs (invoice_id) 
                WHERE status IN ('verifying', 'pending_review')
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("DROP INDEX IF EXISTS payment_proofs_active_invoice_unique");
        }
    }
};
