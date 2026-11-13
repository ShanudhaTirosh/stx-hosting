<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Enhance template_permissions table
        if (Schema::hasTable('template_permissions')) {
            Schema::table('template_permissions', function (Blueprint $table) {
                if (! Schema::hasColumn('template_permissions', 'display_name')) {
                    $table->string('display_name', 150)->nullable()->after('template_name');
                }
                if (! Schema::hasColumn('template_permissions', 'icon_url')) {
                    $table->string('icon_url', 512)->nullable()->after('display_name');
                }
                if (! Schema::hasColumn('template_permissions', 'short_description')) {
                    $table->string('short_description', 500)->nullable()->after('icon_url');
                }
                if (! Schema::hasColumn('template_permissions', 'default_port')) {
                    $table->integer('default_port')->default(80)->after('short_description');
                }
                if (! Schema::hasColumn('template_permissions', 'env_schema')) {
                    $table->json('env_schema')->nullable()->after('default_port');
                }
                if (! Schema::hasColumn('template_permissions', 'min_ram_mb')) {
                    $table->integer('min_ram_mb')->default(512)->after('env_schema');
                }
                if (! Schema::hasColumn('template_permissions', 'min_vcpu')) {
                    $table->decimal('min_vcpu', 3, 1)->default(1.0)->after('min_ram_mb');
                }
                if (! Schema::hasColumn('template_permissions', 'is_featured')) {
                    $table->boolean('is_featured')->default(false)->after('is_allowed');
                }
            });
        }

        // 2. Enhance custom_domains table for live verification and SSL tracking
        if (Schema::hasTable('custom_domains')) {
            Schema::table('custom_domains', function (Blueprint $table) {
                if (! Schema::hasColumn('custom_domains', 'status')) {
                    $table->string('status', 32)->default('pending_dns')->after('verified_at');
                }
                if (! Schema::hasColumn('custom_domains', 'record_type')) {
                    $table->string('record_type', 10)->default('CNAME')->after('status');
                }
                if (! Schema::hasColumn('custom_domains', 'expected_target')) {
                    $table->string('expected_target', 255)->nullable()->after('record_type');
                }
                if (! Schema::hasColumn('custom_domains', 'ssl_issued_at')) {
                    $table->timestamp('ssl_issued_at')->nullable()->after('expected_target');
                }
                if (! Schema::hasColumn('custom_domains', 'ssl_expires_at')) {
                    $table->timestamp('ssl_expires_at')->nullable()->after('ssl_issued_at');
                }
                if (! Schema::hasColumn('custom_domains', 'last_error_message')) {
                    $table->text('last_error_message')->nullable()->after('ssl_expires_at');
                }
                if (! Schema::hasColumn('custom_domains', 'check_attempts')) {
                    $table->integer('check_attempts')->default(0)->after('last_error_message');
                }
            });
        }

        // 3. Enhance service_instances table for template & health checks
        if (Schema::hasTable('service_instances')) {
            Schema::table('service_instances', function (Blueprint $table) {
                if (! Schema::hasColumn('service_instances', 'template_name')) {
                    $table->string('template_name', 100)->nullable()->after('service_type');
                }
                if (! Schema::hasColumn('service_instances', 'health_status')) {
                    $table->string('health_status', 32)->default('healthy')->after('status');
                }
                if (! Schema::hasColumn('service_instances', 'last_health_check_at')) {
                    $table->timestamp('last_health_check_at')->nullable()->after('health_status');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('template_permissions')) {
            Schema::table('template_permissions', function (Blueprint $table) {
                $table->dropColumn([
                    'display_name',
                    'icon_url',
                    'short_description',
                    'default_port',
                    'env_schema',
                    'min_ram_mb',
                    'min_vcpu',
                    'is_featured',
                ]);
            });
        }

        if (Schema::hasTable('custom_domains')) {
            Schema::table('custom_domains', function (Blueprint $table) {
                $table->dropColumn([
                    'status',
                    'record_type',
                    'expected_target',
                    'ssl_issued_at',
                    'ssl_expires_at',
                    'last_error_message',
                    'check_attempts',
                ]);
            });
        }

        if (Schema::hasTable('service_instances')) {
            Schema::table('service_instances', function (Blueprint $table) {
                $table->dropColumn([
                    'template_name',
                    'health_status',
                    'last_health_check_at',
                ]);
            });
        }
    }
};
