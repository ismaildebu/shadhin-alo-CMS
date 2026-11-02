<?php

use App\Models\User;
use App\Support\SuperAdminGuard;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function upazilaForeignKey(): ?array
{
    return collect(Schema::getForeignKeys('users'))
        ->first(fn (array $foreign): bool => in_array('upazila_id', $foreign['columns'], true));
}

function runUpazilaForeignKeyMigration(): void
{
    $migration = require database_path(
        'migrations/2026_11_03_000000_add_upazila_foreign_key_to_users_table.php'
    );

    $migration->up();
}

it('users.upazila_id locations টেবিলের দিকে foreign key', function () {
    $foreign = upazilaForeignKey();

    expect($foreign)->not->toBeNull()
        ->and($foreign['foreign_table'])->toBe('locations')
        ->and($foreign['foreign_columns'])->toBe(['id']);
});

it('অবৈধ upazila_id ডেটাবেস স্তরে প্রত্যাখ্যাত হয়', function () {
    $user = User::factory()->create();

    expect(fn () => DB::table('users')->where('id', $user->id)->update(['upazila_id' => 999999]))
        ->toThrow(QueryException::class);
});

it('সঠিক upazila_id গ্রহণ করা হয়', function () {
    $locationId = DB::table('locations')->insertGetId([
        'parent_id' => null,
        'type' => 'upazila',
        'name_bn' => 'পরীক্ষা',
        'name_en' => 'Test Upazila',
        'slug' => 'test-upazila',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user = User::factory()->create();
    DB::table('users')->where('id', $user->id)->update(['upazila_id' => $locationId]);

    expect((int) DB::table('users')->where('id', $user->id)->value('upazila_id'))
        ->toBe($locationId);
});

it('পুরোনো ডেটাবেসে অরফান ঠিক করে, FK যোগ করে ও সুপার অ্যাডমিন ট্রিগার রাখে', function () {
    Schema::table('users', function (Blueprint $table) {
        $table->dropForeign(['upazila_id']);
    });

    SuperAdminGuard::create();

    $user = User::factory()->create(['name' => 'Owner']);
    DB::table('users')->where('id', $user->id)->update(['upazila_id' => 999999]);
    DB::table('users')->where('id', $user->id)->update(['is_super_admin' => true]);

    runUpazilaForeignKeyMigration();

    expect(DB::table('users')->where('id', $user->id)->value('upazila_id'))->toBeNull()
        ->and(upazilaForeignKey())->not->toBeNull();

    expect(fn () => DB::table('users')->where('id', $user->id)->update(['name' => 'Changed']))
        ->toThrow(QueryException::class);
});
