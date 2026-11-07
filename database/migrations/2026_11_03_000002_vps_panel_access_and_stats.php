<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_instances', function (Blueprint $table) {
            // Key the panel uses to reach this VPS (installed once; the customer can change the root password freely).
            $table->unsignedBigInteger('panel_private_key_id')->nullable();
            // SSH host key fingerprint pinned on first connection, to detect a man in the middle.
            $table->string('host_key_sha256', 64)->nullable();
        });

        Schema::create('vps_stat_samples', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_instance_id')->index();
            $table->float('cpu_percent')->nullable();
            $table->unsignedInteger('mem_total_mb')->default(0);
            $table->unsignedInteger('mem_used_mb')->default(0);
            $table->unsignedInteger('disk_total_gb')->default(0);
            $table->float('disk_used_gb')->default(0);
            $table->float('load1')->nullable();
            $table->unsignedBigInteger('uptime_seconds')->default(0);
            // Raw interface counters, and the traffic since the previous sample (null on the first sample or after a reboot).
            $table->unsignedBigInteger('rx_bytes')->default(0);
            $table->unsignedBigInteger('tx_bytes')->default(0);
            $table->unsignedBigInteger('rx_delta')->nullable();
            $table->unsignedBigInteger('tx_delta')->nullable();
            $table->timestamp('collected_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vps_stat_samples');

        Schema::table('service_instances', function (Blueprint $table) {
            $table->dropColumn(['panel_private_key_id', 'host_key_sha256']);
        });
    }
};
