<?php

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
        DB::unprepared('DROP TRIGGER IF EXISTS protect_super_admin_update');
        DB::unprepared('DROP TRIGGER IF EXISTS protect_super_admin_delete');

        // On an existing database, correct the account without touching
        // its password. On a fresh install there is no account yet: it is
        // created later by SuperAdminSeeder, so this must NOT fail.
        $user = DB::table('users')
            ->where('email', 'mallick.jess@gmail.com')
            ->first(['id', 'is_super_admin']);

        if ($user && (int) $user->is_super_admin === 1) {
            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'name' => 'Devprosad Mallcick',
                    'phone' => '01783057931',
                ]);
        }

        if ($driver === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER protect_super_admin_delete
                BEFORE DELETE ON users
                FOR EACH ROW
                WHEN OLD.is_super_admin = 1
                BEGIN
                    SELECT RAISE(ABORT, 'Super Admin cannot be deleted');
                END;
            SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER protect_super_admin_update
                BEFORE UPDATE ON users
                FOR EACH ROW
                WHEN OLD.is_super_admin = 1
                AND (
                    NEW.id IS NOT OLD.id
                    OR NEW.name IS NOT OLD.name
                    OR NEW.email IS NOT OLD.email
                    OR NEW.email_verified_at IS NOT OLD.email_verified_at
                    OR NEW.phone IS NOT OLD.phone
                    OR NEW.role IS NOT OLD.role
                    OR NEW.is_active IS NOT OLD.is_active
                    OR NEW.bio IS NOT OLD.bio
                    OR NEW.district_id IS NOT OLD.district_id
                    OR NEW.upazila_id IS NOT OLD.upazila_id
                    OR NEW.avatar IS NOT OLD.avatar
                    OR NEW.is_super_admin IS NOT OLD.is_super_admin
                    OR NEW.created_at IS NOT OLD.created_at
                )
                BEGIN
                    SELECT RAISE(
                        ABORT,
                        'Only Super Admin password can be changed'
                    );
                END;
            SQL);

            return;
        }

        // MySQL / MariaDB
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER protect_super_admin_delete
            BEFORE DELETE ON users
            FOR EACH ROW
            BEGIN
                IF OLD.is_super_admin = 1 THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Super Admin cannot be deleted';
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER protect_super_admin_update
            BEFORE UPDATE ON users
            FOR EACH ROW
            BEGIN
                IF OLD.is_super_admin = 1 AND (
                    NOT (NEW.id <=> OLD.id)
                    OR NOT (NEW.name <=> OLD.name)
                    OR NOT (NEW.email <=> OLD.email)
                    OR NOT (NEW.email_verified_at <=> OLD.email_verified_at)
                    OR NOT (NEW.phone <=> OLD.phone)
                    OR NOT (NEW.role <=> OLD.role)
                    OR NOT (NEW.is_active <=> OLD.is_active)
                    OR NOT (NEW.bio <=> OLD.bio)
                    OR NOT (NEW.district_id <=> OLD.district_id)
                    OR NOT (NEW.upazila_id <=> OLD.upazila_id)
                    OR NOT (NEW.avatar <=> OLD.avatar)
                    OR NOT (NEW.is_super_admin <=> OLD.is_super_admin)
                    OR NOT (NEW.created_at <=> OLD.created_at)
                ) THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Only Super Admin password can be changed';
                END IF;
            END
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS protect_super_admin_update');
        DB::unprepared('DROP TRIGGER IF EXISTS protect_super_admin_delete');

        // Intentionally retain the account and its identity.
    }
};