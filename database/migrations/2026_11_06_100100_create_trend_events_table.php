<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the desk gets out of a trending search term:
 *   kind = imported    the article that was pulled in as a draft
 *   kind = suggestion  no usable report — three headlines the desk can write now
 *   kind = forecast    a term expected to trend in the next 24–48 hours
 *
 * The untouched source text lives here, never in the `news` table, so the existing
 * article schema and the existing importer are left exactly as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trend_events', function (Blueprint $table) {
            $table->id();

            $table->char('trend_key', 40)->index();
            $table->string('query', 255);
            $table->string('kind', 16)->index();
            $table->string('status', 16)->default('pending')->index();

            $table->string('language', 8)->default('en');
            $table->string('approx_traffic', 20)->default('');

            // Bengali display text: the imported title or a suggested headline.
            $table->string('title', 500);
            $table->text('headlines')->nullable();

            $table->string('source_host', 120)->default('');
            $table->text('source_link')->nullable();

            $table->string('original_title', 500)->nullable();
            $table->longText('original_content')->nullable();

            $table->boolean('translated')->default(false);
            $table->string('translation_note', 120)->default('');

            $table->string('category_slug', 60)->default('');
            $table->timestamp('occurred_at')->nullable()->index();

            $table->foreignId('news_id')->nullable()->constrained('news')->nullOnDelete();

            $table->timestamps();

            // One trending term yields at most one row of each kind.
            $table->unique(['trend_key', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trend_events');
    }
};
