<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Demo admin (password: "password") for local development ONLY.
        // On production create the admin with: php artisan cms:create-admin you@example.com
        if (! app()->isProduction()) {
            User::factory()->admin()->create([
                'name' => 'Admin',
                'email' => 'admin@example.com',
            ]);

            User::factory()->editor()->create([
                'name' => 'Demo Editor',
                'email' => 'editor@example.com',
            ]);

            User::factory()->reporter()->create([
                'name' => 'Demo Reporter',
                'email' => 'reporter@example.com',
            ]);
        }

        // Seeders
        $this->call([
            CategorySeeder::class,
            LocationSeeder::class,
            NewsSeeder::class,
        ]);
    }
}