<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The first version of the matrix had one "site_manage" permission. It is now
 * split into smaller ones. If anything was saved under the old key, copy that
 * choice to each new permission so no customisation is lost, then drop it.
 */
return new class extends Migration
{
    private const NEW_KEYS = [
        'categories_manage',
        'videos_manage',
        'photocards_manage',
        'polls_manage',
        'ads_manage',
        'layout_manage',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('role_permissions')) {
            return;
        }

        $old = DB::table('role_permissions')
            ->where('permission', 'site_manage')
            ->get(['role', 'allowed']);

        foreach ($old as $row) {
            foreach (self::NEW_KEYS as $key) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role' => $row->role, 'permission' => $key],
                    [
                        'allowed' => $row->allowed,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        DB::table('role_permissions')->where('permission', 'site_manage')->delete();

        Cache::forget('role_permissions.map');
    }

    public function down(): void
    {
        // The split is not reversed: the old single permission no longer exists.
    }
};