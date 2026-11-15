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
        Schema::dropIfExists('oauth_identities');
        Schema::dropIfExists('oauth_settings');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('oauth_settings')) {
            Schema::create('oauth_settings', function (Blueprint $table) {
                $table->id();
                $table->string('provider');
                $table->boolean('enabled')->default(false);
                $table->string('client_id')->nullable();
                $table->text('client_secret')->nullable();
                $table->string('redirect_uri')->nullable();
                $table->string('tenant')->nullable();
                $table->string('base_url')->nullable();
                $table->string('custom_label')->nullable();
                $table->string('scopes')->nullable();
                $table->boolean('allow_registration')->default(false);
                $table->boolean('auto_join_root_team')->default(false);
                $table->boolean('require_email_verified')->default(true);
                $table->boolean('use_pkce')->default(false);
                $table->integer('clock_skew_seconds')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('oauth_identities')) {
            Schema::create('oauth_identities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('provider');
                $table->string('issuer')->nullable();
                $table->string('provider_user_id');
                $table->string('email')->nullable();
                $table->json('raw_claims')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->timestamps();
            });
        }
    }
};
