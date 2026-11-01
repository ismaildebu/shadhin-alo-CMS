<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            if (! Schema::hasColumn('media', 'alt')) {
                $table->string('alt', 255)->nullable()->after('original_name');
            }
            if (! Schema::hasColumn('media', 'focus_x')) {
                $table->unsignedTinyInteger('focus_x')->nullable()->after('alt');
            }
            if (! Schema::hasColumn('media', 'focus_y')) {
                $table->unsignedTinyInteger('focus_y')->nullable()->after('focus_x');
            }
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn(['alt', 'focus_x', 'focus_y']);
        });
    }
};
