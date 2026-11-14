<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Product Variants: Stock status, backing provider & location metadata
        if (! Schema::hasColumn('product_variants', 'stock_status')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->string('stock_status', 32)->default('available')->index(); // available, sold_out
                $table->string('backing_provider', 64)->default('hetzner'); // hetzner, ovh, vultr, digitalocean, contabo, custom
                $table->string('location_name', 128)->nullable(); // e.g. "Falkenstein, Germany", "Helsinki, Finland"
                $table->string('location_flag', 8)->nullable(); // ISO 2-letter country code or emoji
            });
        }

        // 2. VPS Inventory: Upstream cost and provider renewal tracking
        if (! Schema::hasColumn('vps_inventory', 'upstream_cost_cents')) {
            Schema::table('vps_inventory', function (Blueprint $table) {
                $table->integer('upstream_cost_cents')->default(0); // Upstream purchase cost in USD cents
                $table->timestamp('provider_renews_at')->nullable()->index(); // Date when upstream invoice is due
                $table->string('provider_invoice_ref', 128)->nullable(); // Upstream order/invoice ID
            });
        }

        // 3. Service Instances: Financial margin, upstream renewal & customer-managed credential flags
        if (! Schema::hasColumn('service_instances', 'upstream_cost_cents')) {
            Schema::table('service_instances', function (Blueprint $table) {
                $table->integer('upstream_cost_cents')->default(0);
                $table->timestamp('provider_renews_at')->nullable()->index();
                $table->boolean('is_customer_managed_credentials')->default(false);
                $table->timestamp('customer_password_rotated_at')->nullable();
            });
        }

        // 4. Customer Authorized SSH Keys table
        if (! Schema::hasTable('vps_authorized_keys')) {
            Schema::create('vps_authorized_keys', function (Blueprint $table) {
                $table->id();
                $table->string('uuid', 36)->unique();
                $table->foreignId('service_instance_id')->constrained('service_instances')->cascadeOnDelete();
                $table->string('name', 128);
                $table->text('public_key');
                $table->string('fingerprint_sha256', 128)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vps_authorized_keys');

        Schema::table('service_instances', function (Blueprint $table) {
            $table->dropColumn([
                'upstream_cost_cents',
                'provider_renews_at',
                'is_customer_managed_credentials',
                'customer_password_rotated_at',
            ]);
        });

        Schema::table('vps_inventory', function (Blueprint $table) {
            $table->dropColumn([
                'upstream_cost_cents',
                'provider_renews_at',
                'provider_invoice_ref',
            ]);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn([
                'stock_status',
                'backing_provider',
                'location_name',
                'location_flag',
            ]);
        });
    }
};

