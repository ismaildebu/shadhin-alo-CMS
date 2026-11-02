<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_imports', function (Blueprint $table) {
            $table->id();

            $table->string('source', 40);
            $table->char('external_hash', 40);
            $table->char('title_hash', 40)->index();
            $table->text('link');

            $table->foreignId('news_id')->nullable()->constrained('news')->nullOnDelete();

            $table->timestamps();

            // The same source link is never imported twice, even by two runs at once.
            $table->unique(['source', 'external_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_imports');
    }
};
