<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per article that should reach the Facebook page, and the receipt of what
 * was actually sent. The unique news_id is the de-duplication: an article that is
 * unpublished and republished reuses this row instead of posting a second time.
 *
 * Whether a queued row goes out by itself or waits for an editor is decided when it
 * is read, from the category's own tick — so moving a category between the two never
 * needs a rewrite here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_posts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('news_id')->constrained('news')->cascadeOnDelete()->unique();

            // needs_card | queued | posted | failed
            $table->string('status', 24)->default('needs_card')->index();

            $table->text('message')->nullable();
            $table->text('comment')->nullable();

            $table->string('photo_id', 60)->nullable();
            $table->string('post_id', 80)->nullable();

            $table->string('error', 500)->default('');
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_posts');
    }
};
