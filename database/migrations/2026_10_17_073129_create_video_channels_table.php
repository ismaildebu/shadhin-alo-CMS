<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_channels', function (Blueprint $table) {
            $table->id();
            $table->string('channel_id', 40)->unique();
            $table->string('name');
            $table->string('url', 300)->nullable();
            $table->boolean('auto_publish')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_error', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->foreignId('video_channel_id')->nullable()->constrained('video_channels')->nullOnDelete();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('video_channel_id');
            $table->dropSoftDeletes();
        });

        Schema::dropIfExists('video_channels');
    }
};