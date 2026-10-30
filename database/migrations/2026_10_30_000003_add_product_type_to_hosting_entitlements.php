<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hosting_entitlements') && ! Schema::hasColumn('hosting_entitlements', 'product_type')) {
            Schema::table('hosting_entitlements', function (Blueprint $table) {
                $table->string('product_type')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hosting_entitlements') && Schema::hasColumn('hosting_entitlements', 'product_type')) {
            Schema::table('hosting_entitlements', function (Blueprint $table) {
                $table->dropColumn('product_type');
            });
        }
    }
};

