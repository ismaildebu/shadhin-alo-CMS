<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epaper_pages', function (Blueprint $table) {
            $table->id();
            $table->date('edition_date')->unique();
            $table->string('status', 10)->default('draft');
            $table->json('layout')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'edition_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epaper_pages');
    }
};
