<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_check_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->string('status'); // success, failed, skipped
            $table->unsignedInteger('items_discovered')->default(0);
            $table->unsignedInteger('items_new')->default(0);
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['source_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_check_logs');
    }
};