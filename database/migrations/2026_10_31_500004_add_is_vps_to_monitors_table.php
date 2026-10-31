<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('monitors', 'is_vps')) {
            Schema::table('monitors', function (Blueprint $table) {
                $table->boolean('is_vps')->default(false);
            });
        }

        // Set is_vps = true for TCP, ping, or monitors with VPS in name
        DB::table('monitors')
            ->whereIn('type', ['tcp', 'ping'])
            ->orWhereRaw('LOWER(name) LIKE ?', ['%vps%'])
            ->update(['is_vps' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('monitors', 'is_vps')) {
            Schema::table('monitors', function (Blueprint $table) {
                $table->dropColumn('is_vps');
            });
        }
    }
};

