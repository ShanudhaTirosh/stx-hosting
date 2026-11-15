<?php

use App\Models\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Enhance teams table with UUID and seat limit
        Schema::table('teams', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->unsignedInteger('seat_limit')->default(5)->after('custom_server_limit');
        });

        // Populate UUID for existing teams, respecting root team id=0 invariant
        $existingTeams = DB::table('teams')->select('id')->get();
        foreach ($existingTeams as $team) {
            $uuid = ($team->id === 0)
                ? '00000000-0000-0000-0000-000000000000'
                : (string) Str::uuid();

            DB::table('teams')->where('id', $team->id)->update([
                'uuid' => $uuid,
            ]);
        }

        // 2. Enhance team_invitations table for open shareable links, limits, and expiry
        Schema::table('team_invitations', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->unsignedInteger('max_uses')->default(1)->after('access');
            $table->unsignedInteger('uses_count')->default(0)->after('max_uses');
            $table->timestamp('expires_at')->nullable()->after('uses_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            $table->dropColumn(['max_uses', 'uses_count', 'expires_at']);
            $table->string('email')->nullable(false)->change();
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['uuid', 'seat_limit']);
        });
    }
};
