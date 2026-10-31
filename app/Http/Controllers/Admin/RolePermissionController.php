<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Role / permission matrix. Reachable only through the "manage-roles" gate
 * (the super administrator).
 */
class RolePermissionController extends Controller
{
    public function edit(Request $request): View
    {
        $roles = Permissions::editableRoles();

        $selected = (string) $request->query('role', '');

        if (! isset($roles[$selected])) {
            $selected = (string) array_key_first($roles);
        }

        return view('admin.roles.edit', [
            'roles' => $roles,
            'selected' => $selected,
            'groups' => Permissions::groups(),
            'permissions' => Permissions::catalog(),
            'effective' => Permissions::roleMatrix($selected),
            'implied' => Permissions::impliedMap($selected),
            'matrix' => Permissions::matrix(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', 'string', Rule::in(array_keys(Permissions::editableRoles()))],
        ]);

        $role = $data['role'];
        $before = Permissions::roleMatrix($role);

        // Unchecked boxes are not submitted, so "missing" means off.
        Permissions::saveRole($role, (array) $request->input('perm', []));

        Activity::record('roles.updated', null, [
            'role' => $role,
            'before' => $before,
            'after' => Permissions::roleMatrix($role),
        ]);

        return redirect()
            ->route('roles.edit', ['role' => $role])
            ->with('success', 'রোলের অনুমতি সংরক্ষণ করা হয়েছে।');
    }

    public function reset(Request $request): RedirectResponse
    {
        $roles = array_keys(Permissions::editableRoles());

        // With a role: reset only that role. Without: reset every role.
        if ($request->filled('role')) {
            $role = $request->validate([
                'role' => ['string', Rule::in($roles)],
            ])['role'];

            $before = Permissions::roleMatrix($role);

            Permissions::resetRole($role);

            Activity::record('roles.reset', null, [
                'role' => $role,
                'before' => $before,
                'after' => Permissions::roleMatrix($role),
            ]);

            return redirect()
                ->route('roles.edit', ['role' => $role])
                ->with('success', 'এই রোল ডিফল্ট অনুমতিতে ফিরে গেছে।');
        }

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