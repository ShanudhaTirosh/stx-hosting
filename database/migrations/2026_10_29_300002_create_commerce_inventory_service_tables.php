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
        Schema::create('vps_inventory', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->string('datacenter', 64)->default('fsn1');
            $table->string('provider', 64)->default('hetzner'); // hetzner, vultr, digitalocean, ovh, custom
            $table->string('provider_server_id', 128)->nullable();
            $table->string('ipv4_address', 45)->unique();
            $table->string('ipv6_address', 45)->nullable();
            $table->string('gateway', 45)->nullable();
            $table->string('subnet_mask', 45)->nullable();
            $table->string('mac_address', 32)->nullable();
            $table->integer('vcpu')->default(1);
            $table->integer('ram_mb')->default(1024);
            $table->integer('disk_gb')->default(20);
            $table->string('status', 32)->default('available')->index(); // available, reserved, allocated, maintenance, decommissioned
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('service_instances', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('vps_inventory_id')->nullable()->constrained('vps_inventory')->nullOnDelete();
            $table->string('service_type', 32)->index(); // vps, web_app, database, bot
            $table->string('status', 32)->default('provisioning')->index(); // provisioning, active, suspended, terminated
            $table->string('name', 255);
            $table->string('hostname', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('ipv6_address', 45)->nullable();
            $table->string('os_image', 64)->default('debian-12');
            $table->json('network_config')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('renews_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('service_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('service_instance_id')->constrained('service_instances')->cascadeOnDelete();
            $table->string('credential_type', 32); // root_password, ssh_key, api_token, db_password
            $table->string('username', 128)->default('root');
            $table->text('encrypted_secret'); // Encrypted with Laravel Crypt
            $table->integer('ssh_port')->default(22);
            $table->timestamp('last_retrieved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('commercial_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 128)->unique();
            $table->text('value')->nullable();
            $table->string('type', 32)->default('string'); // string, json, boolean, integer
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('commerce_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('action', 64)->index();
            $table->string('target_type', 128)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commerce_audit_logs');
        Schema::dropIfExists('commercial_settings');
        Schema::dropIfExists('service_credentials');
        Schema::dropIfExists('service_instances');
        Schema::dropIfExists('vps_inventory');
    }
};
