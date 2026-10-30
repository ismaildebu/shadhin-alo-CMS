<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    /**
     * Create the permanent super administrator.
     *
     * Safe to run many times: an existing super administrator is never
     * overwritten (its password can only be changed through the app).
     */
    public function run(): void
    {
        $email = (string) config('super_admin.email');
        $password = (string) config('super_admin.password');

        if ($email === '') {
            throw new RuntimeException(
                'config/super_admin.php: email is not configured.'
            );
        }

        $user = User::where('email', $email)->first();

        if ($user && $user->is_super_admin) {
            // Never overwrite an existing Super Admin's password or identity.
            return;
        }

        if ($password === '') {
            throw new RuntimeException(
                'SUPER_ADMIN_PASSWORD must be set in .env before seeding.'
            );
        }

        if (! $user) {
            $user = new User();
            $user->email = $email;
        }

        $user->name = (string) config('super_admin.name');
        $user->phone = config('super_admin.phone');
        $user->role = User::ROLE_ADMIN;
        $user->is_active = true;
        $user->is_super_admin = true;
        $user->email_verified_at ??= now();
        $user->password = $password; // hashed by the model cast
        $user->save();
    }
}