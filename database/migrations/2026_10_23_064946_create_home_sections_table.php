<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_sections', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->unique();
            $table->boolean('visible')->default(true);
            $table->boolean('full_width')->default(false);
            $table->unsignedSmallInteger('item_limit')->nullable();
            $table->unsignedInteger('serial')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('home_sections')->insert([
            ['name' => 'hero',      'visible' => true, 'full_width' => true,  'item_limit' => 3,    'serial' => 10,   'created_at' => $now, 'updated_at' => $now],
            ['name' => 'featured',  'visible' => true, 'full_width' => false, 'item_limit' => 4,    'serial' => 20,   'created_at' => $now, 'updated_at' => $now],
            ['name' => 'videos',    'visible' => true, 'full_width' => false, 'item_limit' => 4,    'serial' => 30,   'created_at' => $now, 'updated_at' => $now],
            ['name' => 'ad_middle', 'visible' => true, 'full_width' => true,  'item_limit' => null, 'serial' => 40,   'created_at' => $now, 'updated_at' => $now],
            ['name' => 'latest',    'visible' => true, 'full_width' => false, 'item_limit' => 12,   'serial' => 1000, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ক্যাটাগরির বর্তমান ক্রম ধরে ১০০, ১১০, ১২০... সংখ্যা বসানো হলো।
        $ids = DB::table('categories')
            ->whereNull('parent_id')
            ->orderBy('home_serial')
            ->orderBy('serial')
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $i => $id) {
            DB::table('categories')->where('id', $id)->update(['home_serial' => 100 + $i * 10]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('home_sections');
    }
};