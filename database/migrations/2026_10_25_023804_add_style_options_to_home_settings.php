<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_sections', function (Blueprint $table) {
            $table->string('layout', 20)->nullable();
            $table->string('bg_color', 7)->nullable();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedTinyInteger('home_limit')->nullable();
            $table->string('home_bg', 7)->nullable();
        });

        // এখনকার চেহারা বজায় রাখতে সেকশনগুলোর বর্তমান স্টাইল ডেটাবেসে তোলা হলো।
        foreach (['hero' => 'classic', 'featured' => 'cards', 'videos' => 'lead', 'latest' => 'rows'] as $name => $layout) {
            DB::table('home_sections')->where('name', $name)->update(['layout' => $layout]);
        }
    }

    public function down(): void
    {
        Schema::table('home_sections', function (Blueprint $table) {
            $table->dropColumn(['layout', 'bg_color']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['home_limit', 'home_bg']);
        });
    }
};