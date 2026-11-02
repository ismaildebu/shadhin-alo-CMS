<?php

use App\Support\SuperAdminGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['sqlite', 'mysql'], true)) {
            throw new RuntimeException(
                'Unsupported database driver for Super Admin protection.'
            );
        }

        // Remove old protection triggers first (they would block the
        // identity correction below), then recreate them at the end.
        SuperAdminGuard::drop();

        // Identity is installation-specific, so it comes from
        // config/super_admin.php (.env) instead of being hardcoded here.
        // On a fresh install there is no account yet: it is created later
        // by SuperAdminSeeder, so this must NOT fail.
        $email = trim((string) config('super_admin.email'));

        if ($email !== '') {
            $user = DB::table('users')
                ->where('email', $email)
                ->first(['id', 'is_super_admin']);

            $identity = array_filter([
                'name' => trim((string) config('super_admin.name')),
                'phone' => trim((string) config('super_admin.phone')),
            ], fn (string $value): bool => $value !== '');

            if ($user && (int) $user->is_super_admin === 1 && $identity !== []) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update($identity);
            }
        }

        SuperAdminGuard::create();
    }

    public function down(): void
    {
        SuperAdminGuard::drop();

        // Intentionally retain the account and its identity.
    }
};
