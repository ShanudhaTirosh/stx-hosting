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
        if (! Schema::hasTable('mail_plans')) {
            Schema::create('mail_plans', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('slug')->unique();
                $table->string('name');
                $table->integer('storage_mb');
                $table->integer('max_mailboxes');
                $table->integer('price_cents')->default(0);
                $table->integer('price_lkr_cents')->nullable();
                $table->string('billing_interval')->default('monthly');
                $table->boolean('is_active')->default(true)->index();
                $table->json('features')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mail_addons')) {
            Schema::create('mail_addons', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('slug')->unique();
                $table->string('name');
                $table->string('type')->index(); // storage, mailbox, domain, feature
                $table->json('specs_json')->nullable();
                $table->integer('price_cents')->default(0);
                $table->string('billing_interval')->default('monthly');
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mail_subscriptions')) {
            Schema::create('mail_subscriptions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->uuid('plan_id')->nullable()->index();
                $table->string('status')->default('active')->index(); // active, past_due, suspended, cancelled
                $table->integer('total_storage_mb')->default(1024);
                $table->integer('max_mailboxes')->default(5);
                $table->integer('max_domains')->default(1);
                $table->json('active_addons')->nullable();
                $table->timestamp('renews_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mail_domains')) {
            Schema::create('mail_domains', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('subscription_id')->nullable()->index();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->string('domain')->unique();
                $table->string('wildduck_domain_id')->nullable();
                $table->string('dkim_selector')->default('wildduck');
                $table->text('dkim_public_key')->nullable();
                $table->string('verification_token')->nullable();
                $table->boolean('is_verified')->default(false)->index();
                $table->json('dns_status')->nullable();
                $table->timestamp('last_checked_at')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mail_mailboxes')) {
            Schema::create('mail_mailboxes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('domain_id')->index();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->string('wildduck_user_id')->unique();
                $table->string('address')->unique();
                $table->string('name');
                $table->integer('quota_mb')->nullable();
                $table->unsignedBigInteger('used_storage_bytes')->default(0);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_catch_all')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mail_aliases')) {
            Schema::create('mail_aliases', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('mailbox_id')->index();
                $table->string('alias_address')->unique();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_aliases');
        Schema::dropIfExists('mail_mailboxes');
        Schema::dropIfExists('mail_domains');
        Schema::dropIfExists('mail_subscriptions');
        Schema::dropIfExists('mail_addons');
        Schema::dropIfExists('mail_plans');
    }
};

