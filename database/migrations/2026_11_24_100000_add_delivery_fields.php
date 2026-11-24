<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->timestamp('baselined_at')->nullable();
        });

        Schema::table('contents', function (Blueprint $table) {
            // seen = baseline (never delivered), pending, downloading, downloaded, sent, failed, expired
            $table->string('status')->default('pending')->index();
            $table->boolean('is_short')->default(false);
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamp('sent_at')->nullable();
        });

        Schema::create('content_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->string('token', 40)->unique();
            $table->string('path'); // relative to rmm.media_root
            $table->string('name');
            $table->string('kind'); // video, photo, document
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->string('delivery')->nullable(); // file+link, link
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('deleted_from_disk_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_files');
        Schema::table('contents', function (Blueprint $table) {
            $table->dropColumn(['status', 'is_short', 'attempts', 'error_message', 'downloaded_at', 'sent_at']);
        });
        Schema::table('sources', function (Blueprint $table) {
            $table->dropColumn('baselined_at');
        });
    }
};
