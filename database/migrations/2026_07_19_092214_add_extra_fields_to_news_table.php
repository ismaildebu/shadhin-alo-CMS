<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {

            $table->string('sub_title')->nullable()->after('title');

            $table->text('summary')->nullable()->after('sub_title');

            $table->string('seo_title')->nullable()->after('summary');

        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {

            $table->dropColumn([
                'sub_title',
                'summary',
                'seo_title'
            ]);

        });
    }
};