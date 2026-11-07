<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Team 0 is the platform team (owner and admins). Coolify's own per-team rule then applies as well.
        DB::table('teams')->where('id', 0)->update(['is_two_factor_required' => true]);
    }

    public function down(): void
    {
        DB::table('teams')->where('id', 0)->update(['is_two_factor_required' => false]);
    }
};
