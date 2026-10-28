<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('show_on_home')->default(true);
            $table->string('home_layout', 20)->nullable();
            $table->string('home_color', 7)->nullable();
            $table->boolean('home_full')->default(false);
            $table->unsignedInteger('home_serial')->default(0);
        });

        // বর্তমান চেহারা বজায় রাখতে আগের ক্রম ও মেনু-সেটিং কপি করা হলো।
        $grammar = DB::getQueryGrammar();

        DB::table('categories')->update([
            'show_on_home' => DB::raw($grammar->wrap('show_in_menu')),
            'home_serial' => DB::raw($grammar->wrap('serial')),
        ]);

        // এখন হোমপেজে যে স্টাইল ও রং চলছে, সেটি ডেটাবেসে তুলে রাখা: slug => [লেআউট, রং, পুরো-চওড়া]
        $defaults = [
            'jayato'          => ['split',    '#d60000', false],
            'international'   => ['mosaic',   '#0f766e', false],
            'entertainment'   => ['scroll',   '#be185d', true],
            'sports'          => ['dark',     '#15803d', true],
            'politics'        => ['grid4',    '#123b68', false],
            'nationwide'      => ['region',   '#0369a1', false],
            'khulna_division' => ['trio',     '#b45309', false],
            'opinion'         => ['opinion',  '#7e22ce', false],
            'editorial'       => ['numbered', '#c2410c', false],
            'sub-editorial'   => ['wide',     '#475569', false],
        ];

        foreach (DB::table('categories')->get(['id', 'slug']) as $row) {
            $d = $defaults[strtolower($row->slug)] ?? null;

            if ($d) {
                DB::table('categories')->where('id', $row->id)->update([
                    'home_layout' => $d[0],
                    'home_color' => $d[1],
                    'home_full' => $d[2],
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['show_on_home', 'home_layout', 'home_color', 'home_full', 'home_serial']);
        });
    }
};