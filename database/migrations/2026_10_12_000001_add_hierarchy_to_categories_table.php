<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // One level of sub-categories: a parent must itself be a top-level category.
            $table->foreignId('parent_id')->nullable()->after('id')
                ->constrained('categories')->restrictOnDelete();

            $table->boolean('show_in_menu')->default(true)->after('status');
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
        });
        // Existing categories keep working unchanged: parent_id = NULL (top level), show_in_menu = true.
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['parent_id', 'show_in_menu', 'meta_title', 'meta_description']);
        });
    }
};
