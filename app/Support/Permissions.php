<?php

namespace App\Support;

use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Role -> permission matrix that can be edited from the admin panel.
 *
 * Storage: the `role_permissions` table holds only the rows that were saved
 * from the panel. A missing row falls back to the defaults below, which equal
 * the behaviour the site had before this feature existed. So the site works
 * the same before the first save, and also if the table is missing.
 *
 * The super administrator is NOT part of the matrix: it always passes
 * (see User::hasPermission() and Gate::before in AppServiceProvider).
 */
class Permissions
{
    private const CACHE_KEY = 'role_permissions.map';

    /** @var array<string, bool>|null  per-request memo of the stored rows */
    private static ?array $memo = null;

    /**
     * Every permission that can be switched on or off per role.
     *
     * defaults: roles that have it when nothing was saved
     * locked:   roles that always keep it (prevents locking admins out)
     *
     * @return array<string, array{label: string, help: string, danger: bool, defaults: list<string>, locked: list<string>}>
     */
    public static function catalog(): array
    {
        return [
            'cms_access' => [
                'label' => 'অ্যাডমিন প্যানেলে প্রবেশ',
                'help' => 'বন্ধ করলে এই রোলের ইউজার প্যানেলে ঢুকতে পারবে না।',
                'danger' => false,
                'defaults' => [User::ROLE_ADMIN, User::ROLE_EDITOR, User::ROLE_REPORTER],
                'locked' => [User::ROLE_ADMIN],
            ],
            'news_review' => [
                'label' => 'সংবাদ ম্যানেজমেন্ট (সম্পাদক-স্তর)',
                'help' => 'সব সংবাদ দেখা ও সম্পাদনা, প্রকাশ ও রিভিউ, ট্র্যাশ এবং মিডিয়া ডিলিট। রিপোর্টারের জন্য বন্ধ থাকলে সে শুধু নিজের সংবাদ লিখতে পারে। সম্পাদকের জন্য বন্ধ করলে সংবাদ সম্পাদনার সুযোগ চলে যায়।',
                'danger' => false,
                'defaults' => [User::ROLE_ADMIN, User::ROLE_EDITOR],
                'locked' => [],
            ],
            'site_manage' => [
                'label' => 'কন্টেন্ট ও সাইট ডিজাইন',
                'help' => 'ক্যাটাগরি, ভিডিও, হোম লেআউট, মেনু, সাইডবার, বিজ্ঞাপন, পোল, আর্টিকেল ডিজাইন ও ফটোকার্ড।',
                'danger' => false,
                'defaults' => [User::ROLE_ADMIN, User::ROLE_EDITOR],
                'locked' => [],
            ],
            'tags_manage' => [
                'label' => 'ট্যাগ ম্যানেজমেন্ট',
                'help' => 'ট্যাগ রিনেম, মার্জ ও ডিলিট।',
                'danger' => false,
                'defaults' => [User::ROLE_ADMIN, User::ROLE_EDITOR],
                'locked' => [],
            ],
            'users_manage' => [
                'label' => 'ইউজার, কার্যকলাপ লগ ও সাইট সেটিংস',
                'help' => 'সতর্কতা: এটা দিলে ইউজার তৈরি, রোল বদল, কার্যকলাপ লগ দেখা ও সাইট সেটিংস বদলানো যাবে।',
                'danger' => true,
                'defaults' => [User::ROLE_ADMIN],
                'locked' => [User::ROLE_ADMIN],
            ],
        ];
    }

    /**
     * Roles shown as columns in the matrix (role value => label).
     *
     * @return array<string, string>
     */
    public static function editableRoles(): array
    {
        return [
            User::ROLE_ADMIN => 'অ্যাডমিন',
            User::ROLE_EDITOR => 'সম্পাদক',
            User::ROLE_REPORTER => 'রিপোর্টার',
        ];
    }

    public static function isLocked(?string $role, string $permission): bool
    {
        return $role !== null
            && in_array($role, self::catalog()[$permission]['locked'] ?? [], true);
    }

    /**
     * Whether a role holds a permission (the super administrator is
     * handled by the caller, not here).
     */
    public static function roleAllows(?string $role, string $permission): bool
    {
        $catalog = self::catalog();

        if ($role === null || ! isset($catalog[$permission])) {
            return false;
        }

        if (self::isLocked($role, $permission)) {
            return true;
        }

        $map = self::map();
        $key = $role.'|'.$permission;

        if (array_key_exists($key, $map)) {
            return $map[$key];
        }

        return in_array($role, $catalog[$permission]['defaults'], true);
    }

    /**
     * Effective matrix for the editable roles: [role][permission] => bool.
     *
     * @return array<string, array<string, bool>>
     */
    public static function matrix(): array
    {
        $matrix = [];

        foreach (array_keys(self::editableRoles()) as $role) {
            foreach (array_keys(self::catalog()) as $permission) {
                $matrix[$role][$permission] = self::roleAllows($role, $permission);
            }
        }

        return $matrix;
    }

    /**
     * Forget the cached rows (call after every change).
     */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        self::$memo = null;
    }

    /**
     * Remove every saved row, so all roles return to the defaults.
     */
    public static function resetToDefaults(): void
    {
        RolePermission::query()->delete();
        self::flush();
    }

    /**
     * @return array<string, bool>  "role|permission" => allowed
     */
    private static function map(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            return self::$memo = $cached;
        }

        try {
            $map = [];

            foreach (RolePermission::query()->get(['role', 'permission', 'allowed']) as $row) {
                $map[$row->role.'|'.$row->permission] = (bool) $row->allowed;
            }
        } catch (\Throwable) {
            // Table not migrated yet: use the defaults, and do not cache this.
            return self::$memo = [];
        }

        Cache::forever(self::CACHE_KEY, $map);

        return self::$memo = $map;
    }
}