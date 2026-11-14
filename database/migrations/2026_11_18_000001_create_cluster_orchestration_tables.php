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
        if (! Schema::hasTable('cluster_nodes')) {
            Schema::create('cluster_nodes', function (Blueprint $table) {
                $table->id();
                $table->string('uuid', 36)->unique();
                $table->string('name', 128);
                $table->string('status', 32)->default('provisioning')->index(); // provisioning, ready, draining, maintenance, offline
                $table->string('public_ip', 45)->unique();
                $table->string('private_ip', 45)->nullable();
                $table->integer('ssh_port')->default(22);
                $table->foreignId('server_id')->nullable()->constrained('servers')->nullOnDelete();
                $table->string('region', 64)->default('default')->index();
                $table->integer('cpu_cores')->default(1);
                $table->integer('total_ram_mb')->default(1024);
                $table->integer('reserved_ram_mb')->default(256);
                $table->integer('total_disk_gb')->default(25);
                $table->integer('max_apps')->default(50);
                $table->integer('weight_adjustment')->default(0);
                $table->json('labels')->nullable();
                $table->json('allowed_templates')->nullable();
                $table->string('enrollment_token', 128)->nullable()->index();
                $table->float('current_cpu_load')->default(0);
                $table->integer('current_ram_used_mb')->default(0);
                $table->integer('current_disk_used_gb')->default(0);
                $table->timestamp('last_heartbeat_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('node_placement_rules')) {
            Schema::create('node_placement_rules', function (Blueprint $table) {
                $table->id();
                $table->string('rule_type', 32)->index(); // pin_team, pin_template, require_label
                $table->string('target_identifier', 128)->nullable();
                $table->foreignId('cluster_node_id')->nullable()->constrained('cluster_nodes')->cascadeOnDelete();
                $table->json('label_selector')->nullable();
                $table->integer('priority')->default(10);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('node_migration_jobs')) {
            Schema::create('node_migration_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid', 36)->unique();
                $table->foreignId('service_instance_id')->constrained('service_instances')->cascadeOnDelete();
                $table->foreignId('source_node_id')->constrained('cluster_nodes')->cascadeOnDelete();
                $table->foreignId('target_node_id')->constrained('cluster_nodes')->cascadeOnDelete();
                $table->string('status', 32)->default('pending')->index(); // pending, syncing, cutover, completed, failed
                $table->bigInteger('volume_bytes_transferred')->default(0);
                $table->float('downtime_duration_seconds')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('service_instances') && ! Schema::hasColumn('service_instances', 'cluster_node_id')) {
            Schema::table('service_instances', function (Blueprint $table) {
                $table->foreignId('cluster_node_id')->nullable()->after('product_variant_id')->constrained('cluster_nodes')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('service_instances') && Schema::hasColumn('service_instances', 'cluster_node_id')) {
            Schema::table('service_instances', function (Blueprint $table) {
                $table->dropForeign(['cluster_node_id']);
                $table->dropColumn('cluster_node_id');
            });
        }

        Schema::dropIfExists('node_migration_jobs');
        Schema::dropIfExists('node_placement_rules');
        Schema::dropIfExists('cluster_nodes');
    }
};

