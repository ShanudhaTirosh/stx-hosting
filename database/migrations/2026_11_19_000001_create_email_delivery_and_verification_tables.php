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
        if (! Schema::hasTable('email_delivery_logs')) {
            Schema::create('email_delivery_logs', function (Blueprint $table) {
                $table->id();
                $table->string('recipient')->index();
                $table->string('subject');
                $table->string('template_key')->nullable()->index();
                $table->string('status')->default('sent')->index(); // queued, sent, delivered, bounced, failed
                $table->string('mailer')->nullable(); // smtp, relay, failover
                $table->text('error_message')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('login_verification_codes')) {
            Schema::create('login_verification_codes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('code_hash');
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->integer('attempts')->default(0);
                $table->boolean('is_used')->default(false)->index();
                $table->timestamp('expires_at')->index();
                $table->timestamps();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'login_2fa_policy')) {
                $table->string('login_2fa_policy')->default('new_device'); // always, new_device, sensitive_only
            }
            if (! Schema::hasColumn('users', 'email_notifications_preferences')) {
                $table->json('email_notifications_preferences')->nullable();
            }
            if (! Schema::hasColumn('users', 'unsubscribe_token')) {
                $table->string('unsubscribe_token', 64)->nullable()->unique();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_verification_codes');
        Schema::dropIfExists('email_delivery_logs');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'unsubscribe_token')) {
                $table->dropColumn('unsubscribe_token');
            }
            if (Schema::hasColumn('users', 'email_notifications_preferences')) {
                $table->dropColumn('email_notifications_preferences');
            }
            if (Schema::hasColumn('users', 'login_2fa_policy')) {
                $table->dropColumn('login_2fa_policy');
            }
        });
    }
};
