<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per trending search term seen in a run — the audit log behind the
 * "আলোচিত সার্চ" page. Records why a term was imported or left out, so a low
 * count is always explainable and never quietly hidden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trend_checks', function (Blueprint $table) {
            $table->id();

            $table->string('geo', 8)->index();
            $table->char('trend_key', 40)->index();
            $table->string('query', 255);
            $table->string('approx_traffic', 20)->default('');
            $table->string('language', 8)->default('');

            // The publisher page that was inspected, if any.
            $table->string('candidate_host', 120)->default('');
            $table->text('candidate_link')->nullable();

            // Verified publish date taken from the article page itself; null = not found.
            $table->timestamp('published_at')->nullable();

            $table->string('verdict', 20)->index();
            $table->string('reason', 255)->default('');

            $table->foreignId('news_id')->nullable()->constrained('news')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trend_checks');
    }
};
