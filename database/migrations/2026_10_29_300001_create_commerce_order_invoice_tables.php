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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('pending')->index(); // pending, processing, completed, cancelled, refunded
            $table->integer('subtotal_cents');
            $table->integer('discount_cents')->default(0);
            $table->integer('tax_cents')->default(0);
            $table->integer('total_cents');
            $table->string('currency', 3)->default('USD');
            $table->string('customer_notes', 1000)->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('pricing_tier_id')->constrained('pricing_tiers')->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->integer('unit_price_cents');
            $table->integer('total_price_cents');
            $table->string('hostname', 255)->nullable();
            $table->text('public_ssh_key')->nullable();
            $table->string('datacenter', 64)->default('fsn1');
            $table->json('config_options')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->string('invoice_number', 64)->unique(); // INV-YYYYMM-XXXX
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('unpaid')->index(); // unpaid, payment_pending, paid, cancelled, refunded
            $table->integer('subtotal_cents');
            $table->integer('tax_cents')->default(0);
            $table->integer('total_cents');
            $table->string('currency', 3)->default('USD');
            $table->timestamp('due_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->string('description', 512);
            $table->integer('quantity')->default(1);
            $table->integer('unit_price_cents');
            $table->integer('total_price_cents');
            $table->timestamps();
        });

        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('payment_method', 64); // bank_transfer, crypto, helapay_manual
            $table->string('transaction_reference', 255)->nullable();
            $table->string('file_path', 1024);
            $table->string('file_mime', 128);
            $table->integer('file_size_bytes');
            $table->string('sha256_hash', 64);
            $table->string('status', 32)->default('pending_review')->index(); // pending_review, approved, rejected
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('payment_proof_id')->nullable()->constrained('payment_proofs')->nullOnDelete();
            $table->string('gateway', 64); // manual, bank_transfer, stripe, crypto
            $table->string('transaction_id', 255)->unique();
            $table->integer('amount_cents');
            $table->string('currency', 3)->default('USD');
            $table->string('status', 32)->default('successful')->index(); // pending, successful, failed, refunded
            $table->json('gateway_payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payment_proofs');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
