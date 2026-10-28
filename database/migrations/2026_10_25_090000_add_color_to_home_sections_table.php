<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('home_sections', 'color')) {
            Schema::table('home_sections', function (Blueprint $table) {
                $table->string('color', 7)->nullable()->after('bg_color');
            });
        }

        // The lead section used to be locked to full width. It can now be switched off in the
        // admin, so keep today's look until someone does.
        DB::table('home_sections')->where('name', 'hero')->update(['full_width' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('home_sections', 'color')) {
            Schema::table('home_sections', function (Blueprint $table) {
                $table->dropColumn('color');
            });
        }
    }
};
