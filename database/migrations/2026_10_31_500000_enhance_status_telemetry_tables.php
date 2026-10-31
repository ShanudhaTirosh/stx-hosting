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
        Schema::table('monitors', function (Blueprint $table) {
            if (!Schema::hasColumn('monitors', 'target_url')) {
                $table->string('target_url', 1024)->nullable()->after('type');
            }
            if (!Schema::hasColumn('monitors', 'expected_status_code')) {
                $table->integer('expected_status_code')->default(200)->after('port');
            }
            if (!Schema::hasColumn('monitors', 'keyword_search')) {
                $table->string('keyword_search')->nullable()->after('expected_status_code');
            }
            if (!Schema::hasColumn('monitors', 'custom_headers')) {
                $table->json('custom_headers')->nullable()->after('keyword_search');
            }
            if (!Schema::hasColumn('monitors', 'display_order')) {
                $table->integer('display_order')->default(0)->after('last_latency_ms');
            }
        });

        if (!Schema::hasTable('incident_updates')) {
            Schema::create('incident_updates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('system_incident_id')->constrained('system_incidents')->cascadeOnDelete();
                $table->string('status'); // investigating, identified, monitoring, resolved, update
                $table->text('message');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        Schema::table('system_incidents', function (Blueprint $table) {
            if (!Schema::hasColumn('system_incidents', 'incident_type')) {
                $table->string('incident_type')->default('incident')->after('title'); // incident, maintenance
            }
            if (!Schema::hasColumn('system_incidents', 'impact_level')) {
                $table->string('impact_level')->default('minor')->after('incident_type'); // none, minor, major, critical
            }
            if (!Schema::hasColumn('system_incidents', 'affected_monitors')) {
                $table->json('affected_monitors')->nullable()->after('impact_level'); // [monitor_ids] or ["all"]
            }
            if (!Schema::hasColumn('system_incidents', 'scheduled_start_at')) {
                $table->timestamp('scheduled_start_at')->nullable()->after('message');
            }
            if (!Schema::hasColumn('system_incidents', 'scheduled_end_at')) {
                $table->timestamp('scheduled_end_at')->nullable()->after('scheduled_start_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incident_updates');

        Schema::table('system_incidents', function (Blueprint $table) {
            $table->dropColumn([
                'incident_type',
                'impact_level',
                'affected_monitors',
                'scheduled_start_at',
                'scheduled_end_at',
            ]);
        });

        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn([
                'target_url',
                'expected_status_code',
                'keyword_search',
                'custom_headers',
                'display_order',
            ]);
        });
    }
};
