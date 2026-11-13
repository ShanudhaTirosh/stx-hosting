<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_restores', function (Blueprint $table) {
            $table->unsignedBigInteger('target_database_id')->nullable()->after('safety_execution_id');
            $table->string('target_database_type', 128)->nullable()->after('target_database_id');
        });
    }

    public function down(): void
    {
        Schema::table('backup_restores', function (Blueprint $table) {
            $table->dropColumn(['target_database_id', 'target_database_type']);
        });
    }
};

