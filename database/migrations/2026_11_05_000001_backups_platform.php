<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Small key/value store for owner-set platform options (which R2 storage holds backups, per-team quotas).
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('backup_restores', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('execution_id')->index(); // the backup being restored
            $table->unsignedBigInteger('safety_execution_id')->nullable(); // the backup taken just before
            $table->string('status', 20)->default('queued')->index(); // queued, snapshotting, restoring, started, failed
            $table->string('message', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_restores');
        Schema::dropIfExists('platform_settings');
    }
};
