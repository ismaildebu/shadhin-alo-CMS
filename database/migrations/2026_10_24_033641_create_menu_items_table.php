<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);             // home | videos | link
            $table->string('label', 60);
            $table->string('url', 500)->nullable();
            $table->boolean('new_tab')->default(false);
            $table->boolean('visible')->default(true);
            $table->boolean('more')->default(false);
            $table->unsignedInteger('serial')->default(0);
            $table->timestamps();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedInteger('menu_serial')->default(0);
            $table->boolean('menu_more')->default(false);
        });

        $now = now();

        DB::table('menu_items')->insert([
            ['type' => 'home',   'label' => 'হোম',   'visible' => true, 'more' => false, 'serial' => 10,  'created_at' => $now, 'updated_at' => $now],
            ['type' => 'videos', 'label' => 'ভিডিও', 'visible' => true, 'more' => false, 'serial' => 900, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ক্যাটাগরির বর্তমান ক্রম ধরে সংখ্যা বসানো; আগের মতো মেনুতে থাকা প্রথম ১২টি মূল মেনুতে, বাকিগুলো "অন্যান্য" এ।
        $rows = DB::table('categories')
            ->whereNull('parent_id')
            ->orderBy('serial')
            ->orderBy('id')
            ->get(['id', 'show_in_menu']);

        $shown = 0;

        foreach ($rows as $i => $row) {
            $more = false;

            if ($row->show_in_menu) {
                $more = $shown >= 12;
                $shown++;
            }

            DB::table('categories')->where('id', $row->id)->update([
                'menu_serial' => 100 + $i * 10,
                'menu_more' => $more,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['menu_serial', 'menu_more']);
        });

        Schema::dropIfExists('menu_items');
    }
};