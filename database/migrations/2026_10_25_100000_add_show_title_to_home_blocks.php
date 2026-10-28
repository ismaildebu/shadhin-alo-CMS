<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A per-block switch for the heading shown above each home-page section / category. */
    public function up(): void
    {
        if (! Schema::hasColumn('home_sections', 'show_title')) {
            Schema::table('home_sections', function (Blueprint $table) {
                $table->boolean('show_title')->default(true);
            });
        }

        if (! Schema::hasColumn('categories', 'home_show_title')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->boolean('home_show_title')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('home_sections', 'show_title')) {
            Schema::table('home_sections', function (Blueprint $table) {
                $table->dropColumn('show_title');
            });
        }

        if (Schema::hasColumn('categories', 'home_show_title')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('home_show_title');
            });
        }
    }
};
