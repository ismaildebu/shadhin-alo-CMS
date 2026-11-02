<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

function runSuperAdminRepairMigration(): void
{
    $migration = require database_path(
        'migrations/2026_10_29_064715_repair_and_protect_super_admin_account.php'
    );

    $migration->up();
}

it('কনফিগে দেওয়া super admin-এর নাম ও ফোন ঠিক করে', function () {
    $owner = User::factory()->create([
        'email' => 'owner@example.test',
        'name' => 'Old Name',
    ]);
    DB::table('users')->where('id', $owner->id)->update([
        'phone' => null,
        'is_super_admin' => true,
    ]);

    config([
        'super_admin.email' => 'owner@example.test',
        'super_admin.name' => 'Configured Owner',
        'super_admin.phone' => '01000000000',
    ]);

    runSuperAdminRepairMigration();

    $owner->refresh();

    expect($owner->name)->toBe('Configured Owner')
        ->and($owner->phone)->toBe('01000000000');
});

it('অন্য super admin-এর তথ্য বদলায় না', function () {
    $other = User::factory()->create([
        'email' => 'other@example.test',
        'name' => 'Other Admin',
    ]);
    DB::table('users')->where('id', $other->id)->update([
        'phone' => '01999999999',
        'is_super_admin' => true,
    ]);

    config([
        'super_admin.email' => 'owner@example.test',
        'super_admin.name' => 'Configured Owner',
        'super_admin.phone' => '01000000000',
    ]);

    runSuperAdminRepairMigration();

    $other->refresh();

    expect($other->name)->toBe('Other Admin')
        ->and($other->phone)->toBe('01999999999');
});

it('কনফিগের ইমেইল সাধারণ ব্যবহারকারীর হলে তার তথ্য বদলায় না', function () {
    $user = User::factory()->create([
        'email' => 'owner@example.test',
        'name' => 'Normal User',
    ]);
    DB::table('users')->where('id', $user->id)->update(['phone' => '01711111111']);

    config([
        'super_admin.email' => 'owner@example.test',
        'super_admin.name' => 'Configured Owner',
        'super_admin.phone' => '01000000000',
    ]);

    runSuperAdminRepairMigration();

    $user->refresh();

    expect($user->name)->toBe('Normal User')
        ->and($user->phone)->toBe('01711111111');
});

it('কনফিগে ইমেইল না থাকলে কিছুই বদলায় না', function () {
    $owner = User::factory()->create([
        'email' => 'owner@example.test',
        'name' => 'Old Name',
    ]);
    DB::table('users')->where('id', $owner->id)->update(['is_super_admin' => true]);

    config([
        'super_admin.email' => '',
        'super_admin.name' => 'Configured Owner',
        'super_admin.phone' => '01000000000',
    ]);

    runSuperAdminRepairMigration();

    expect($owner->fresh()->name)->toBe('Old Name');
});
