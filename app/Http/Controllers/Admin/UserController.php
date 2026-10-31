<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\Activity;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));

        $users = User::query()
            ->withCount('news')
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.addcslashes($term, '%_\\').'%';

                $q->where(function ($w) use ($like) {
                    $w->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'term'));
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'user' => new User,
            'districts' => $this->districts(),
            'upazilas' => $this->upazilas(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->validateReporterLocation($data);

        return DB::transaction(function () use ($data) {
            $user = new User(
                collect($data)
                    ->only(['name', 'email', 'password', 'phone', 'bio'])
                    ->all()
            );

            $user->role = $data['role'];
            $user->is_active = $data['is_active'];
            $user->district_id = $this->districtFor($data);
            $user->upazila_id = $this->upazilaFor($data);
            $user->email_verified_at = now();
            $user->save();

            Activity::record('user.created', $user, [
                'role' => $user->role,
                'is_active' => $user->is_active,
                'district_id' => $user->district_id,
                'upazila_id' => $user->upazila_id,
            ]);

            return redirect()
                ->route('users.index')
                ->with('success', 'ইউজার তৈরি হয়েছে।');
        });
    }

    public function edit(Request $request, User $user): View
    {
        $this->ensureNotProtected($request, $user);

        return view('admin.users.edit', [
            'user' => $user,
            'districts' => $this->districts(),
            'upazilas' => $this->upazilas(),
        ]);
    }

    public function update(
        UserRequest $request,
        User $user
    ): RedirectResponse {
        // Nobody except the super administrator may touch that account.
        $this->ensureNotProtected($request, $user);

        $data = $request->validated();

        // The super administrator account is immutable except for its
        // password (UserRequest only validates the password for it).
        if ($user->isSuperAdmin()) {
            return $this->updateSuperAdminPassword($user, $data);
        }

        $this->validateReporterLocation($data);

        $isSelf = $user->is($request->user());

        // Prevent an administrator from locking themselves out.
        if ($isSelf && (
            $data['role'] !== $user->role
            || ! $data['is_active']
        )) {
            return back()
                ->withInput()
                ->withErrors([
                    'role' => 'নিজের রোল বদলানো বা নিজেকে নিষ্ক্রিয় করা যাবে না।',
                ]);
        }

        // Protect the last active administrator.
        if ($this->wouldRemoveLastAdmin(
            $user,
            $data['role'],
            $data['is_active']
        )) {
            return back()
                ->withInput()
                ->withErrors([
                    'role' => 'সর্বশেষ সক্রিয় অ্যাডমিনকে নামানো বা নিষ্ক্রিয় করা যাবে না।',
                ]);
        }

        $before = [
            'role' => $user->role,
            'is_active' => $user->is_active,
            'district_id' => $user->district_id,
            'upazila_id' => $user->upazila_id,
        ];

        return DB::transaction(function () use (
            $data,
            $user,
            $before
        ) {
            $user->fill(
                collect($data)
                    ->only(['name', 'email', 'phone', 'bio'])
                    ->all()
            );

            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }

            $user->role = $data['role'];
            $user->is_active = $data['is_active'];
            $user->district_id = $this->districtFor($data);
            $user->upazila_id = $this->upazilaFor($data);
            $user->save();

            Activity::record('user.updated', $user, [
                'before' => $before,
                'after' => [
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                    'district_id' => $user->district_id,
                    'upazila_id' => $user->upazila_id,
                ],
                'password_changed' => ! empty($data['password']),
            ]);

            return redirect()
                ->route('users.index')
                ->with('success', 'ইউজার আপডেট হয়েছে।');
        });
    }

    public function destroy(
        Request $request,
        User $user
    ): RedirectResponse {
        // The super administrator can never be deleted, by anyone.
        if ($user->isSuperAdmin()) {
            return back()->withErrors([
                'user' => 'সুপার অ্যাডমিন অ্যাকাউন্ট কখনো মুছে ফেলা যায় না।',
            ]);
        }

        if ($user->is($request->user())) {
            return back()->withErrors([
                'user' => 'নিজের অ্যাকাউন্ট মুছতে পারবেন না।',
            ]);
        }

        if ($this->wouldRemoveLastAdmin($user, null, false)) {
            return back()->withErrors([
                'user' => 'সর্বশেষ সক্রিয় অ্যাডমিনকে মুছে ফেলা যাবে না।',
            ]);
        }

        if ($user->news()->withTrashed()->exists()) {
            return back()->withErrors([
                'user' => 'এই ইউজারের সংবাদ আছে। মুছে না ফেলে "নিষ্ক্রিয়" করুন।',
            ]);
        }

        $email = $user->email;
        $name = $user->name;

        $user->delete();

        Activity::record('user.deleted', null, [
            'email' => $email,
            'name' => $name,
        ]);

        return redirect()
            ->route('users.index')
            ->with('success', 'ইউজার মুছে ফেলা হয়েছে।');
    }

    /**
     * Only the super administrator may open or change its own account.
     */
    private function ensureNotProtected(Request $request, User $target): void
    {
        abort_if(
            $target->isSuperAdmin() && ! $target->is($request->user()),
            403,
            'সুপার অ্যাডমিনের অ্যাকাউন্টে পরিবর্তন করা যাবে না।'
        );
    }

    /**
     * The only change allowed on the super administrator is the password.
     *
     * @param  array<string, mixed>  $data
     */
    private function updateSuperAdminPassword(
        User $user,
        array $data
    ): RedirectResponse {
        if (empty($data['password'])) {
            return redirect()
                ->route('users.index')
                ->with(
                    'success',
                    'কোনো পরিবর্তন হয়নি। সুপার অ্যাডমিনের শুধু পাসওয়ার্ড বদলানো যায়।'
                );
        }

        DB::transaction(function () use ($user, $data) {
            $user->password = $data['password'];
            $user->save();

            Activity::record('user.password_changed', $user, [
                'password_changed' => true,
            ]);
        });

        return redirect()
            ->route('users.index')
            ->with('success', 'সুপার অ্যাডমিনের পাসওয়ার্ড আপডেট হয়েছে।');
    }

    /**
     * Return the existing district list used by the user forms.
     */
    private function districts()
    {
        return Location::districts()
            ->with('parent:id,name_bn')
            ->orderBy('name_en')
            ->get(['id', 'parent_id', 'name_bn']);
    }

    /**
     * Return existing upazilas with their parent district IDs.
     */
    private function upazilas()
    {
        return Location::query()
            ->where('type', Location::UPAZILA)
            ->whereNotNull('parent_id')
            ->orderBy('name_en')
            ->get(['id', 'parent_id', 'name_bn', 'name_en']);
    }

    /**
     * Only reporters may have a district assignment.
     */
    private function districtFor(array $data): ?int
    {
        if (! User::isReporterRole($data['role'])) {
            return null;
        }

        $upazilaId = $data['upazila_id'] ?? null;

        // When an upazila is selected, derive its district from the
        // database rather than trusting a separately submitted district.
        if ($upazilaId !== null && $upazilaId !== '') {
            return Location::query()
                ->whereKey($upazilaId)
                ->where('type', Location::UPAZILA)
                ->value('parent_id');
        }

        return isset($data['district_id'])
            ? (int) $data['district_id']
            : null;
    }

    /**
     * Only reporters may have an upazila assignment.
     */
    private function upazilaFor(array $data): ?int
    {
        if (! User::isReporterRole($data['role'])) {
            return null;
        }

        $upazilaId = $data['upazila_id'] ?? null;

        return $upazilaId !== null && $upazilaId !== ''
            ? (int) $upazilaId
            : null;
    }

    /**
     * Ensure a reporter's selected upazila belongs to the selected
     * district. Other roles must not retain reporter locations.
     */
    private function validateReporterLocation(array $data): void
    {
        if (! User::isReporterRole($data['role'])) {
            return;
        }

        $upazilaId = $data['upazila_id'] ?? null;
        $districtId = $data['district_id'] ?? null;

        if ($upazilaId === null || $upazilaId === '') {
            return;
        }

        $upazila = Location::query()
            ->whereKey($upazilaId)
            ->where('type', Location::UPAZILA)
            ->first(['id', 'parent_id']);

        if (! $upazila) {
            throw ValidationException::withMessages([
                'upazila_id' => 'নির্বাচিত উপজেলা সঠিক নয়।',
            ]);
        }

        if (
            $districtId !== null
            && $districtId !== ''
            && (int) $districtId !== (int) $upazila->parent_id
        ) {
            throw ValidationException::withMessages([
                'upazila_id' => 'নির্বাচিত উপজেলা এই জেলার অন্তর্ভুক্ত নয়।',
            ]);
        }
    }

    /**
     * Prevent removing or deactivating the last active administrator.
     */
    private function wouldRemoveLastAdmin(
        User $user,
        ?string $newRole,
        bool $active
    ): bool {
        $isActiveAdminNow = $user->role === User::ROLE_ADMIN
            && $user->is_active;

        $staysActiveAdmin = $newRole === User::ROLE_ADMIN
            && $active;

        if (! $isActiveAdminNow || $staysActiveAdmin) {
            return false;
        }

        $otherActiveAdmins = User::query()
            ->where('role', User::ROLE_ADMIN)
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->count();

        return $otherActiveAdmins === 0;
    }
}