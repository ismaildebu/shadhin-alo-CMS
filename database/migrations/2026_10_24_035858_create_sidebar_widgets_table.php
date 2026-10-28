<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sidebar_widgets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->unique();
            $table->boolean('visible')->default(true);
            $table->unsignedSmallInteger('item_limit')->nullable();
            $table->unsignedInteger('serial')->default(0);
            $table->timestamps();
        });

        $now = now();

        // এখনকার সাইডবারের ক্রম ও চেহারা বজায় রাখা হলো; ভিডিও ও ফেসবুক শুরুতে লুকানো।
        DB::table('sidebar_widgets')->insert([
            ['name' => 'ad_top',    'visible' => true,  'item_limit' => null, 'serial' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'tabs',      'visible' => true,  'item_limit' => 5,    'serial' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'ad_middle', 'visible' => true,  'item_limit' => null, 'serial' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'videos',    'visible' => false, 'item_limit' => 3,    'serial' => 40, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'facebook',  'visible' => false, 'item_limit' => null, 'serial' => 50, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'tags',      'visible' => true,  'item_limit' => 12,   'serial' => 60, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'ad_bottom', 'visible' => true,  'item_limit' => null, 'serial' => 70, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sidebar_widgets');
    }
};