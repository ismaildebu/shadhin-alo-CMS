<?php

use App\Support\SuperAdminGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->hasUpazilaForeignKey()) {
            return;
        }

        $orphans = DB::table('users')
            ->whereNotNull('upazila_id')
            ->whereNotIn('upazila_id', DB::table('locations')->select('id'))
            ->exists();

        $triggersDropped = false;

        if ($orphans) {
            // The protection trigger blocks every identity change,
            // including upazila_id, so it must be off while clearing.
            SuperAdminGuard::drop();
            $triggersDropped = true;

            DB::table('users')
                ->whereNotNull('upazila_id')
                ->whereNotIn('upazila_id', DB::table('locations')->select('id'))
                ->update(['upazila_id' => null]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('upazila_id')
                ->references('id')
                ->on('locations')
                ->nullOnDelete();
        });

        // Adding a foreign key rebuilds SQLite tables, which drops triggers.
        if ($triggersDropped || DB::getDriverName() === 'sqlite') {
            SuperAdminGuard::create();
        }
    }

    public function down(): void
    {
        if (! $this->hasUpazilaForeignKey()) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['upazila_id']);
        });

        if (DB::getDriverName() === 'sqlite') {
            SuperAdminGuard::create();
        }
    }

    private function hasUpazilaForeignKey(): bool
    {
        return collect(Schema::getForeignKeys('users'))
            ->contains(fn (array $foreign): bool => in_array(
                'upazila_id',
                $foreign['columns'] ?? [],
                true
            ));
    }
};
