<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Until v4.5.0, a HEALTHCHECK in the Dockerfile was used even when the STX healthcheck was enabled.
     * Since v4.5.1 an enabled STX healthcheck replaces it. Existing applications with a Dockerfile
     * HEALTHCHECK get the STX healthcheck disabled, so their deployments keep the Dockerfile healthcheck.
     */
    public function up(): void
    {
        DB::table('applications')
            ->where('custom_healthcheck_found', true)
            ->where('health_check_enabled', true)
            ->update(['health_check_enabled' => false]);
    }

    public function down(): void
    {
        // This data migration cannot identify which applications had the STX healthcheck enabled.
    }
};
