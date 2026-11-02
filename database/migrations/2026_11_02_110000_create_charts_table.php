<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('type', 20)->default('column');
            $table->string('unit', 20)->nullable();
            $table->string('source', 200)->nullable();
            $table->json('data');
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('serial')->default(0);
            $table->timestamps();

            $table->index(['is_published', 'serial']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charts');
    }
};
