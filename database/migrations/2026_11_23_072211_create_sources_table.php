<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // instagram, youtube, website, telegram, bale, x, custom
            $table->string('name');
            $table->string('url')->nullable();
            $table->string('external_id')->nullable();
            $table->string('username')->nullable();
            $table->string('handle')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('check_interval_minutes')->default(60);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_successful_check_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('configuration')->nullable();
            $table->json('credentials')->nullable();
            $table->json('media_settings')->nullable();
            $table->json('notification_settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'is_active']);
            $table->index('last_checked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};