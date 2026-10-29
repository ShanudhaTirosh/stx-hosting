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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->string('category', 64)->index(); // vps, web_hosting, database, bot_hosting, v2ray
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->string('icon_url', 512)->nullable();
            $table->boolean('is_popular')->default(false);
            $table->string('badge', 64)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('slug', 255);
            $table->integer('vcpu')->default(1);
            $table->integer('ram_mb')->default(1024);
            $table->integer('disk_gb')->default(20);
            $table->string('disk_type', 32)->default('nvme');
            $table->integer('bandwidth_gb')->default(1000);
            $table->json('specs_json')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['product_id', 'slug']);
        });

        Schema::create('pricing_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->string('billing_type', 32); // monthly, quarterly, semi_annual, annual
            $table->integer('price_cents'); // Price in USD cents
            $table->integer('price_lkr_cents')->nullable(); // Price in LKR cents
            $table->integer('setup_fee_cents')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['product_variant_id', 'billing_type']);
        });

        Schema::create('price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pricing_tier_id')->constrained('pricing_tiers')->cascadeOnDelete();
            $table->integer('old_price_cents');
            $table->integer('new_price_cents');
            $table->string('currency', 3)->default('USD');
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_histories');
        Schema::dropIfExists('pricing_tiers');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};
