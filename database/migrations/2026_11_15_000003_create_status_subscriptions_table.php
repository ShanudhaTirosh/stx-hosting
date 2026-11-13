<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('email')->index();
            $table->string('verification_token', 64)->nullable()->index();
            $table->timestamp('verified_at')->nullable();
            $table->string('unsubscribe_token', 64)->unique();
            $table->json('subscribed_components')->nullable(); // null = all components
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['email', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_subscriptions');
    }
};

