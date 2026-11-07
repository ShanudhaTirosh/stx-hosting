<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Highest delivery-SLA alert already sent to staff: 0, 24, 48 or 72 hours after payment approval.
            $table->unsignedSmallInteger('delivery_alert_hours')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('delivery_alert_hours');
        });
    }
};
