<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('polls', function (Blueprint $table) {
            if (! Schema::hasColumn('polls', 'image')) {
                $table->string('image', 500)->nullable()->after('question');
            }
        });
    }

    public function down(): void
    {
        Schema::table('polls', function (Blueprint $table) {
            if (Schema::hasColumn('polls', 'image')) {
                $table->dropColumn('image');
            }
        });
    }
};
