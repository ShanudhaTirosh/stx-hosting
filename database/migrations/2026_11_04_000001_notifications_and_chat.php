<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('subject', 200);
            $table->string('heading', 200);
            $table->text('body');
            $table->string('cta_text', 80)->nullable();
            $table->string('cta_path', 200)->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_routes', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->string('event', 64)->index();
            $table->string('label', 120)->nullable();
            $table->text('webhook_url'); // encrypted by the model
            $table->boolean('is_enabled')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('chat_threads', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('user_id'); // who opened it
            $table->string('subject', 150);
            $table->string('status', 16)->default('open')->index(); // open, closed
            $table->unsignedBigInteger('assigned_to')->nullable()->index();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->boolean('last_sender_is_staff')->default(false);
            $table->timestamp('client_read_at')->nullable();
            $table->timestamp('staff_read_at')->nullable();
            $table->timestamp('client_emailed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->unsignedBigInteger('chat_thread_id')->index();
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_staff')->default(false);
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('chat_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->unsignedBigInteger('chat_message_id')->index();
            $table->string('path', 512);
            $table->string('original_name', 255);
            $table->string('mime', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_attachments');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_threads');
        Schema::dropIfExists('notification_routes');
        Schema::dropIfExists('email_templates');
    }
};
