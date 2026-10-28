<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Registers the online poll as a managed sidebar widget (order and show/hide in the admin sidebar page). */
    public function up(): void
    {
        if (DB::table('sidebar_widgets')->where('name', 'poll')->exists()) {
            return;
        }

        $now = now();

        // serial 25 = between the tabs (20) and the middle advert (30)
        DB::table('sidebar_widgets')->insert([
            'name' => 'poll',
            'visible' => true,
            'item_limit' => null,
            'serial' => 25,
            'layout' => null,
            'accent' => null,
            'box_height' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('sidebar_widgets')->where('name', 'poll')->delete();
    }
};
