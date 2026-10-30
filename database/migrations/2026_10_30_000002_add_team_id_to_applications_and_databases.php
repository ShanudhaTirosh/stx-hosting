<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('applications') && ! Schema::hasColumn('applications', 'team_id')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->foreignId('team_id')->nullable()->index();
            });
        }

        if (Schema::hasTable('standalone_postgresqls') && ! Schema::hasColumn('standalone_postgresqls', 'team_id')) {
            Schema::table('standalone_postgresqls', function (Blueprint $table) {
                $table->foreignId('team_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('applications') && Schema::hasColumn('applications', 'team_id')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->dropColumn('team_id');
            });
        }

        if (Schema::hasTable('standalone_postgresqls') && Schema::hasColumn('standalone_postgresqls', 'team_id')) {
            Schema::table('standalone_postgresqls', function (Blueprint $table) {
                $table->dropColumn('team_id');
            });
        }
    }
};

