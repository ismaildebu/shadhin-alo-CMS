<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException(
                'Demo accounts must not be seeded in production.'
            );
        }

        if (! Schema::hasColumn('users', 'upazila_id')) {
            throw new RuntimeException(
                'Run the upazila_id migration before seeding demo accounts.'
            );
        }

        $accounts = [
            [
                'name' => 'Demo Admin 2',
                'email' => 'demo.admin2@example.com',
                'role' => User::ROLE_ADMIN,
            ],
            [
                'name' => 'Demo Editor 2',
                'email' => 'demo.editor2@example.com',
                'role' => User::ROLE_EDITOR,
            ],
            [
                'name' => 'Demo Editor 3',
                'email' => 'demo.editor3@example.com',
                'role' => User::ROLE_EDITOR,
            ],
        ];

        for ($i = 1; $i <= 14; $i++) {
            $number = str_pad((string) $i, 2, '0', STR_PAD_LEFT);

            $accounts[] = [
                'name' => "Demo Reporter {$number}",
                'email' => "demo.reporter{$number}@example.com",
                'role' => User::ROLE_REPORTER,
            ];
        }

        $upazilas = Location::query()
            ->where('type', Location::UPAZILA)
            ->whereNotNull('parent_id')
            ->orderBy('id')
            ->get(['id', 'parent_id'])
            ->values();

        if ($upazilas->count() < 14) {
            throw new RuntimeException(
                'At least 14 existing upazila records are required.'
            );
        }

        $reporterIndex = 0;

        foreach ($accounts as $account) {
            $location = null;

            if ($account['role'] === User::ROLE_REPORTER) {
                $location = $upazilas[$reporterIndex] ?? null;
                $reporterIndex++;
            }

            // Never overwrite an existing account.
            if (User::query()->where('email', $account['email'])->exists()) {
                continue;
            }

            $user = new User();

            $user->name = $account['name'];
            $user->email = $account['email'];
            $user->password = Hash::make('password');
            $user->role = $account['role'];
            $user->is_active = true;
            $user->email_verified_at = now();

            $user->district_id = $location?->parent_id;
            $user->upazila_id = $location?->id;

            $user->save();
        }

        $this->command?->info(
            'Demo account seeding completed. Existing accounts were preserved.'
        );
    }
}
