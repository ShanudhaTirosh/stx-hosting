<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->string('type', 32)->default('bank_transfer'); // bank_transfer, crypto, wallet, other
            $table->string('label', 120);
            $table->string('currency', 8)->default('ANY'); // ANY, USD, LKR
            $table->text('instructions')->nullable();
            $table->json('details')->nullable(); // [{label, value}, ...] shown to clients
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('admin_notes');
            $table->unsignedSmallInteger('attempt_no')->default(1)->after('rejection_reason');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('pdf_path', 1024)->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('pdf_path');
        });

        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->dropColumn(['rejection_reason', 'attempt_no']);
        });

        Schema::dropIfExists('payment_methods');
    }
};
