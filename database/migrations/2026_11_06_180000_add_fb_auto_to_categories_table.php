<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categories switched on here publish themselves; every other article waits on the
 * "ফেসবুক পোস্ট" page until an editor presses পাঠান.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('fb_auto')->default(false)->after('show_in_menu');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('fb_auto');
        });
    }
};
