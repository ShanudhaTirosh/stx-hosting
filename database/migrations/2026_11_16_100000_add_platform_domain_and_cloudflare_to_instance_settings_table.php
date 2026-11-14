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
        Schema::table('instance_settings', function (Blueprint $table) {
            $table->string('base_platform_domain')->nullable();
            $table->string('apps_domain')->nullable();
            $table->text('cloudflare_api_token')->nullable();
            $table->string('cloudflare_zone_id')->nullable();
            $table->timestamp('platform_domains_initialized_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instance_settings', function (Blueprint $table) {
            $table->dropColumn([
                'base_platform_domain',
                'apps_domain',
                'cloudflare_api_token',
                'cloudflare_zone_id',
                'platform_domains_initialized_at',
            ]);
        });
    }
};

