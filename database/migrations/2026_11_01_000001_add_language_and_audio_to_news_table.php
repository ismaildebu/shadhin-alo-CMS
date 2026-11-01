<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            if (!Schema::hasColumn('news', 'lang')) {
                $table->string('lang', 10)->default('bn')->index()->after('id');
            }
            if (!Schema::hasColumn('news', 'translations')) {
                $table->json('translations')->nullable()->after('content');
            }
            if (!Schema::hasColumn('news', 'audio_url')) {
                $table->string('audio_url')->nullable()->after('translations');
            }
            if (!Schema::hasColumn('news', 'audio_duration')) {
                $table->integer('audio_duration')->nullable()->comment('Duration in seconds')->after('audio_url');
            }
            if (!Schema::hasColumn('news', 'tts_status')) {
                $table->enum('tts_status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->after('audio_duration');
            }
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn(['lang', 'translations', 'audio_url', 'audio_duration', 'tts_status']);
        });
    }
};