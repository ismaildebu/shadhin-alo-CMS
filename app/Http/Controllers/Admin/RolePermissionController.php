<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\RolePermission;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Role / permission matrix. Reachable only through the "manage-roles" gate
 * (the super administrator).
 */
class RolePermissionController extends Controller
{
    public function edit(): View
    {
        return view('admin.roles.edit', [
            'roles' => Permissions::editableRoles(),
            'permissions' => Permissions::catalog(),
            'matrix' => Permissions::matrix(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $input = (array) $request->input('perm', []);
        $before = Permissions::matrix();
        $now = now();
        $rows = [];

        foreach (array_keys(Permissions::editableRoles()) as $role) {
            foreach (array_keys(Permissions::catalog()) as $permission) {
                // Unchecked boxes are not submitted, so "missing" means off.
                $allowed = ! empty($input[$role][$permission]);

                // Locked cells (e.g. admins keep panel + user management)
                // are always on, whatever was submitted.
                if (Permissions::isLocked($role, $permission)) {
                    $allowed = true;
                }

                $rows[] = [
                    'role' => $role,
                    'permission' => $permission,
                    'allowed' => $allowed,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        RolePermission::upsert(
            $rows,
            ['role', 'permission'],
            ['allowed', 'updated_at']
        );

        Permissions::flush();

        Activity::record('roles.updated', null, [
            'before' => $before,
            'after' => Permissions::matrix(),
        ]);

        return redirect()
            ->route('roles.edit')
            ->with('success', 'রোলের অনুমতি সংরক্ষণ করা হয়েছে।');
    }

    public function reset(): RedirectResponse
    {
        $before = Permissions::matrix();

        Permissions::resetToDefaults();

        Activity::record('roles.reset', null, [
            'before' => $before,
            'after' => Permissions::matrix(),
        ]);

        return redirect()
            ->route('roles.edit')
            ->with('success', 'সব রোল ডিফল্ট অনুমতিতে ফিরে গেছে।');
    }
}