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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_reference', 64)->nullable()->index()->after('uuid');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('payment_reference', 64)->nullable()->index()->after('invoice_number');
        });

        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->string('dhash', 64)->nullable()->index()->after('sha256_hash');
            $table->decimal('confidence_score', 5, 4)->nullable()->after('status');
            $table->unsignedBigInteger('extracted_amount_cents')->nullable()->after('confidence_score');
            $table->string('extracted_reference', 255)->nullable()->after('extracted_amount_cents');
            $table->string('extracted_account_tail', 32)->nullable()->after('extracted_reference');
            $table->timestamp('extracted_datetime')->nullable()->after('extracted_account_tail');
            $table->json('ai_analysis')->nullable()->after('extracted_datetime');
            $table->json('discrepancies')->nullable()->after('ai_analysis');
            $table->string('verification_basis', 64)->nullable()->after('discrepancies'); // ai_confidence, bank_match, admin_override
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->dropColumn([
                'dhash',
                'confidence_score',
                'extracted_amount_cents',
                'extracted_reference',
                'extracted_account_tail',
                'extracted_datetime',
                'ai_analysis',
                'discrepancies',
                'verification_basis',
            ]);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('payment_reference');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('payment_reference');
        });
    }
};

