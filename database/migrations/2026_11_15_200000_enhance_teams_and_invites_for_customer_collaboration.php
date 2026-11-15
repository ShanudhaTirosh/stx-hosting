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
        Schema::table('team_invitations', function (Blueprint $table) {
            if (! Schema::hasColumn('team_invitations', 'invited_by_user_id')) {
                $table->foreignId('invited_by_user_id')->nullable()->after('team_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('team_invitations', 'target_user_id')) {
                $table->foreignId('target_user_id')->nullable()->after('invited_by_user_id')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('team_user', function (Blueprint $table) {
            if (! Schema::hasColumn('team_user', 'access')) {
                $table->json('access')->nullable()->after('role');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_user', function (Blueprint $table) {
            if (Schema::hasColumn('team_user', 'access')) {
                $table->dropColumn('access');
            }
        });

        Schema::table('team_invitations', function (Blueprint $table) {
            if (Schema::hasColumn('team_invitations', 'target_user_id')) {
                $table->dropForeign(['target_user_id']);
                $table->dropColumn('target_user_id');
            }
            if (Schema::hasColumn('team_invitations', 'invited_by_user_id')) {
                $table->dropForeign(['invited_by_user_id']);
                $table->dropColumn('invited_by_user_id');
            }
        });
    }
};
