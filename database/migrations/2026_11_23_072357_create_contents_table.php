<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->text('caption')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->string('media_type')->default('unknown'); // text,image,video,audio,document,carousel,live,article,unknown
            $table->timestamp('published_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['source_id', 'external_id']);
            $table->index('content_hash');
            $table->index('canonical_url');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contents');
    }
};