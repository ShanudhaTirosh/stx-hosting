<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hosting_products')) {
            Schema::create('hosting_products', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('type')->default('app_hosting');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('features')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hosting_plans')) {
            Schema::create('hosting_plans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('hosting_product_id')->nullable()->constrained('hosting_products')->nullOnDelete();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->integer('price_cents')->default(0);
                $table->string('billing_interval')->default('monthly');
                $table->integer('term_days')->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hosting_limits')) {
            Schema::create('hosting_limits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->nullable()->constrained('teams')->cascadeOnDelete();
                $table->foreignId('hosting_plan_id')->nullable()->constrained('hosting_plans')->cascadeOnDelete();
                $table->integer('max_projects')->nullable()->default(5);
                $table->integer('max_deployments')->nullable()->default(50);
                $table->integer('max_applications')->nullable()->default(5);
                $table->integer('max_databases')->nullable()->default(3);
                $table->integer('max_servers')->nullable()->default(2);
                $table->integer('max_running_containers')->nullable()->default(10);
                $table->decimal('max_cpu', 8, 2)->nullable()->default(2.00);
                $table->integer('max_ram_mb')->nullable()->default(4096);
                $table->integer('max_storage_gb')->nullable()->default(50);
                $table->integer('max_bandwidth_gb')->nullable()->default(200);
                $table->integer('max_smtp_accounts')->nullable()->default(0);
                $table->integer('max_emails_per_day')->nullable()->default(0);
                $table->json('allowed_technologies')->nullable();
                $table->json('allowed_template_categories')->nullable();
                $table->timestamps();
            });

            // Seed default global limit row (team_id = null, hosting_plan_id = null)
            DB::table('hosting_limits')->insert([
                'team_id'                 => null,
                'hosting_plan_id'         => null,
                'max_projects'            => 5,
                'max_deployments'         => 50,
                'max_applications'        => 5,
                'max_databases'           => 3,
                'max_servers'             => 2,
                'max_running_containers'  => 10,
                'max_cpu'                 => 2.00,
                'max_ram_mb'              => 4096,
                'max_storage_gb'          => 50,
                'max_bandwidth_gb'        => 200,
                'max_smtp_accounts'       => 0,
                'max_emails_per_day'      => 0,
                'created_at'              => now(),
                'updated_at'              => now(),
            ]);
        }

        if (! Schema::hasTable('hosting_entitlements')) {
            Schema::create('hosting_entitlements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->foreignId('hosting_product_id')->nullable()->constrained('hosting_products')->nullOnDelete();
                $table->foreignId('hosting_plan_id')->nullable()->constrained('hosting_plans')->nullOnDelete();
                $table->string('status')->default('active');
                $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('expires_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hosting_invoices')) {
            Schema::create('hosting_invoices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->foreignId('hosting_plan_id')->nullable()->constrained('hosting_plans')->nullOnDelete();
                $table->integer('amount_cents')->default(0);
                $table->string('currency')->default('USD');
                $table->string('status')->default('unpaid');
                $table->timestamp('due_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('template_permissions')) {
            Schema::create('template_permissions', function (Blueprint $table) {
                $table->id();
                $table->string('template_name')->index();
                $table->foreignId('hosting_plan_id')->nullable()->constrained('hosting_plans')->cascadeOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->cascadeOnDelete();
                $table->boolean('is_globally_enabled')->default(true);
                $table->boolean('is_allowed')->default(true);
                $table->string('category')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('template_permissions');
        Schema::dropIfExists('hosting_invoices');
        Schema::dropIfExists('hosting_entitlements');
        Schema::dropIfExists('hosting_limits');
        Schema::dropIfExists('hosting_plans');
        Schema::dropIfExists('hosting_products');
    }
};

