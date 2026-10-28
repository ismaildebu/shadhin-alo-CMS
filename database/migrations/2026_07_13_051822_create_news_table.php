<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news', function (Blueprint $table) {

            $table->id();

            $table->foreignId('category_id')->constrained()->onDelete('cascade');

            $table->string('title');
            $table->string('slug')->unique();

            $table->string('image')->nullable();

            $table->longText('description');

            $table->string('reporter')->nullable();

            $table->boolean('breaking')->default(false);
            $table->boolean('featured')->default(false);

            $table->boolean('status')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news');
    }
};