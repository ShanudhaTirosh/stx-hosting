<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Monitors Table
        Schema::create('monitors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('public_label');
            $table->string('group_name')->default('Core Infrastructure');
            $table->string('type')->default('tcp'); // tcp, http, ping
            $table->string('target_host'); // raw IP or host, never exposed publicly
            $table->integer('port')->default(22);
            $table->integer('interval_seconds')->default(60);
            $table->integer('timeout_ms')->default(3000);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('current_status')->default('operational'); // operational, degraded, down
            $table->decimal('uptime_percentage', 5, 2)->default(100.00);
            $table->integer('last_latency_ms')->default(15);
            $table->timestamp('last_checked_at')->nullable();
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->index(['is_public', 'is_active']);
            $table->index('group_name');
        });

        // 2. Monitor Checks History (for 90-day uptime bars & latency tracking)
        Schema::create('monitor_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained('monitors')->onDelete('cascade');
            $table->string('status', 16); // up, down, degraded
            $table->integer('response_time_ms')->default(0);
            $table->string('error_message')->nullable();
            $table->timestamp('checked_at')->useCurrent();

            $table->index(['monitor_id', 'checked_at']);
        });

        // 3. System Incidents & Maintenance Notices
        Schema::create('system_incidents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->string('status')->default('investigating'); // investigating, identified, monitoring, resolved
            $table->string('severity')->default('operational'); // operational, minor, major, critical
            $table->text('message');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_incidents');
        Schema::dropIfExists('monitor_checks');
        Schema::dropIfExists('monitors');
    }
};

