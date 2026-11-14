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
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('account_holder', 255)->nullable()->after('label');
            $table->string('account_number', 128)->nullable()->after('account_holder');
            $table->string('bank_name', 128)->nullable()->after('account_number');
            $table->string('branch', 128)->nullable()->after('bank_name');
            $table->string('logo_path', 512)->nullable()->after('branch');
            $table->json('allowed_categories')->nullable()->after('details'); // null = all, or ['vps', 'app_hosting', 'database', 'mail_hosting']
            $table->unsignedBigInteger('min_amount_cents')->nullable()->after('allowed_categories');
            $table->unsignedBigInteger('max_amount_cents')->nullable()->after('min_amount_cents');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn([
                'account_holder',
                'account_number',
                'bank_name',
                'branch',
                'logo_path',
                'allowed_categories',
                'min_amount_cents',
                'max_amount_cents',
            ]);
        });
    }
};

