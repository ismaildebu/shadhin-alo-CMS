<?php

namespace App\Support;

use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Role -> permission matrix that can be edited from the admin panel.
 *
 * Storage: the `role_permissions` table holds only the rows that were saved
 * from the panel. A missing row falls back to the defaults below, so the site
 * works before the first save, and also if the table is missing.
 *
 * The super administrator is NOT part of the matrix: it always passes
 * (see User::hasPermission() and Gate::before in AppServiceProvider).
 *
 * Catalog keys:
 *   defaults    roles that have the permission when nothing was saved
 *   locked      roles that always keep it (prevents locking admins out)
 *   implied_by  permissions that already include this one (shown as on)
 */
class Permissions
{
    private const CACHE_KEY = 'role_permissions.map';

    /** @var array<string, bool>|null  per-request memo of the stored rows */
    private static ?array $memo = null;

    /**
     * Groups used to organise the permission list in the panel.
     *
     * @return array<string, string>
     */
    public static function groups(): array
    {
        return [
            'panel' => 'প্যানেল',
            'news' => 'সংবাদ',
            'content' => 'কন্টেন্ট ও মিডিয়া',
            'design' => 'সাইট ডিজাইন',
            'system' => 'সিস্টেম',
        ];
    }

    /**
     * Every permission that can be switched on or off per role.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function catalog(): array
    {
        $admin = User::ROLE_ADMIN;
        $editor = User::ROLE_EDITOR;
        $deputy = User::ROLE_DEPUTY_EDITOR;
        $newsEditor = User::ROLE_NEWS_EDITOR;
        $jointEditor = User::ROLE_JOINT_NEWS_EDITOR;
        $subEditor = User::ROLE_SUB_EDITOR;
        $chiefReporter = User::ROLE_CHIEF_REPORTER;
        $mediaEditor = User::ROLE_MEDIA_EDITOR;
        $adManager = User::ROLE_AD_MANAGER;
        $designer = User::ROLE_DESIGNER;

        return [
            'cms_access' => [
                'group' => 'panel',
                'label' => 'অ্যাডমিন প্যানেলে প্রবেশ',
                'short' => 'প্যানেল',
                'help' => 'বন্ধ করলে এই রোলের ইউজার প্যানেলে ঢুকতে পারবে না। চালু থাকলে সে নিজের লেখা সংবাদ তৈরি ও সম্পাদনা করতে পারে (রিভিউয়ের আগ পর্যন্ত)।',
                'danger' => false,
                'defaults' => array_keys(self::editableRoles()),
                'locked' => [$admin],
            ],

            'news_review' => [
                'group' => 'news',
                'label' => 'সংবাদ ম্যানেজমেন্ট (সম্পাদক-স্তর)',
                'short' => 'সংবাদ',
                'help' => 'সব সংবাদ দেখা ও সম্পাদনা, প্রকাশ ও রিভিউ, ট্র্যাশ ও পুনরুদ্ধার, মিডিয়া ডিলিট। বন্ধ থাকলে সে প্রকাশ করতে পারে না।',
                'danger' => false,
                'defaults' => [$admin, $editor, $deputy, $newsEditor, $jointEditor],
                'locked' => [],
            ],

            'news_edit_all' => [
                'group' => 'news',
                'label' => 'সব সংবাদ দেখা ও সম্পাদনা (প্রকাশ ছাড়া)',
                'short' => 'সম্পাদনা',
                'help' => 'অন্যের লেখা সংবাদ খুলে সম্পাদনা বা প্রুফ দেখা যায়, কিন্তু প্রকাশ বা ট্র্যাশ করা যায় না। "সংবাদ ম্যানেজমেন্ট" চালু থাকলে এটা নিজে থেকেই অন্তর্ভুক্ত।',
                'danger' => false,
                'defaults' => [$subEditor, $chiefReporter],
                'locked' => [],
                'implied_by' => ['news_review'],
            ],

            'categories_manage' => [
                'group' => 'content',
                'label' => 'ক্যাটাগরি ম্যানেজমেন্ট',
                'short' => 'ক্যাটাগরি',
                'help' => 'ক্যাটাগরি তৈরি, সম্পাদনা ও মোছা।',
                'danger' => false,
                'defaults' => [$admin, $editor, $deputy, $newsEditor],
                'locked' => [],
            ],

            'tags_manage' => [
                'group' => 'content',
                'label' => 'ট্যাগ ম্যানেজমেন্ট',
                'short' => 'ট্যাগ',
                'help' => 'ট্যাগ রিনেম, মার্জ ও ডিলিট।',
                'danger' => false,
                'defaults' => [$admin, $editor, $deputy, $newsEditor, $jointEditor, $subEditor],
                'locked' => [],
            ],

            'videos_manage' => [
                'group' => 'content',
                'label' => 'ভিডিও গ্যালারি',
                'short' => 'ভিডিও',
                'help' => 'ভিডিও ও ইউটিউব চ্যানেল যোগ, সিঙ্ক ও মোছা।',
                'danger' => false,
                'defaults' => [$admin, $editor, $deputy, $newsEditor, $mediaEditor],
                'locked' => [],
            ],

            'photocards_manage' => [
                'group' => 'content',
                'label' => 'ফটোকার্ড সেন্টার',
                'short' => 'ফটোকার্ড',
                'help' => 'ফটোকার্ডের ডিফল্ট ও কিউ ম্যানেজ।',
                'danger' => false,
                'defaults' => [$admin, $editor, $deputy, $newsEditor, $jointEditor, $mediaEditor],
                'locked' => [],
            ],

            'polls_manage' => [
                'group' => 'content',
                'label' => 'অনলাইন ভোট',
                'short' => 'ভোট',
                'help' => 'পোল তৈরি, সম্পাদনা ও মোছা।',
                'danger' => false,
                'defaults' => [$admin, $editor, $deputy, $newsEditor, $jointEditor],
                'locked' => [],
            ],

            'ads_manage' => [
                'group' => 'content',
                'label' => 'বিজ্ঞাপন ম্যানেজমেন্ট',
                'short' => 'বিজ্ঞাপন',
                'help' => 'বিজ্ঞাপন যোগ ও বদল। স্ক্রিপ্ট বা কোড-ধরনের বিজ্ঞাপন নিরাপত্তার কারণে এখনো শুধু অ্যাডমিন বদলাতে পারে।',
                'danger' => false,
                'defaults' => [$admin, $editor, $adManager],
                'locked' => [],
            ],

            'layout_manage' => [
                'group' => 'design',
                'label' => 'সাইট লেআউট ও ডিজাইন',
                'short' => 'ডিজাইন',
                'help' => 'হোমপেজ সাজানো, মেনু, সাইডবার, সংবাদ পেজ ডিজাইন ও ডেটা ভিজ্যুয়াল বিল্ডার।',
                'danger' => false,
                'defaults' => [$admin, $editor, $deputy, $newsEditor, $designer],
                'locked' => [],
            ],

            'users_manage' => [
                'group' => 'system',
                'label' => 'ইউজার, কার্যকলাপ লগ ও সাইট সেটিংস',
                'short' => 'ইউজার',
                'help' => 'সতর্কতা: এটা দিলে ইউজার তৈরি, রোল বদল, কার্যকলাপ লগ দেখা ও সাইট সেটিংস বদলানো যাবে।',
                'danger' => true,
                'defaults' => [$admin],
                'locked' => [$admin],
            ],
        ];
    }

    /**
     * Roles that can be edited in the matrix (role value => label).
     * The super administrator and the plain "user" role are not editable.
     *
     * @return array<string, string>
     */
    public static function editableRoles(): array
    {
        $roles = [];

        foreach (User::ROLES as $role => $label) {
            if (in_array($role, [User::ROLE_SUPER_ADMIN, User::ROLE_USER], true)) {
                continue;
            }

            $roles[$role] = trim(explode(' (', $label)[0]);
        }

        return $roles;
    }

    public static function isLocked(?string $role, string $permission): bool
    {
        return $role !== null
            && in_array($role, self::catalog()[$permission]['locked'] ?? [], true);
    }

    /**
     * Whether another permission the role holds already includes this one.
     */
    public static function isImplied(?string $role, string $permission): bool
    {
        foreach (self::catalog()[$permission]['implied_by'] ?? [] as $other) {
            if (self::roleAllows($role, $other)) {
                return true;
            }
        }

        return false;
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

        if (self::isLocked($role, $permission) || self::isImplied($role, $permission)) {
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
     * Effective permissions of one role: permission => bool.
     *
     * @return array<string, bool>
     */
    public static function roleMatrix(string $role): array
    {
        $result = [];

        foreach (array_keys(self::catalog()) as $permission) {
            $result[$permission] = self::roleAllows($role, $permission);
        }

        return $result;
    }

    /**
     * Which of a role's permissions are on only because another includes them.
     *
     * @return array<string, bool>
     */
    public static function impliedMap(string $role): array
    {
        $result = [];

        foreach (array_keys(self::catalog()) as $permission) {
            $result[$permission] = ! self::isLocked($role, $permission)
                && self::isImplied($role, $permission);
        }

        return $result;
    }

    /**
     * Effective matrix for every editable role: [role][permission] => bool.
     *
     * @return array<string, array<string, bool>>
     */
    public static function matrix(): array
    {
        $matrix = [];

        foreach (array_keys(self::editableRoles()) as $role) {
            $matrix[$role] = self::roleMatrix($role);
        }

        return $matrix;
    }

    /**
     * Save the checked permissions of one role.
     *
     * @param  array<string, mixed>  $checked  permission => truthy when ticked
     */
    public static function saveRole(string $role, array $checked): void
    {
        if (! array_key_exists($role, self::editableRoles())) {
            throw new InvalidArgumentException("Role [$role] cannot be edited.");
        }

        $catalog = self::catalog();
        $want = [];

        foreach (array_keys($catalog) as $permission) {
            // Locked cells (e.g. admins keep panel + user management) stay on.
            $want[$permission] = self::isLocked($role, $permission)
                ? true
                : ! empty($checked[$permission]);
        }

        $now = now();
        $rows = [];

        foreach ($want as $permission => $allowed) {
            // A cell that another ticked permission already includes is
            // derived, never stored (so it follows if that one is turned off).
            $impliedBy = $catalog[$permission]['implied_by'] ?? [];

            if (array_filter($impliedBy, fn ($other) => $want[$other] ?? false) !== []) {
                continue;
            }

            $rows[] = [
                'role' => $role,
                'permission' => $permission,
                'allowed' => $allowed,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        RolePermission::upsert($rows, ['role', 'permission'], ['allowed', 'updated_at']);

        self::flush();
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
     * Remove the saved rows of one role, so it returns to the defaults.
     */
    public static function resetRole(string $role): void
    {
        RolePermission::query()->where('role', $role)->delete();
        self::flush();
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