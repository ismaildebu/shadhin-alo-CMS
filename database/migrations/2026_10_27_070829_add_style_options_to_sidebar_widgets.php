<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sidebar_widgets', function (Blueprint $table) {
            $table->string('layout', 20)->nullable();
            $table->string('accent', 7)->nullable();
            $table->unsignedSmallInteger('box_height')->nullable();
        });

        // এখনকার চেহারা বজায় রাখা: ট্যাব উইজেটের বর্তমান স্টাইল "ক্রমিক নম্বর"
        DB::table('sidebar_widgets')->where('name', 'tabs')->update(['layout' => 'numbered']);
    }

    public function down(): void
    {
        Schema::table('sidebar_widgets', function (Blueprint $table) {
            $table->dropColumn(['layout', 'accent', 'box_height']);
        });
    }
};