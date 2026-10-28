<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\Activity;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));

        $users = User::query()
            ->withCount('news')
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'term'));
    }

    public function create(): View
    {
        return view('admin.users.create', ['user' => new User, 'districts' => $this->districts()]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = new User(collect($data)->only(['name', 'email', 'password', 'phone', 'bio'])->all());
        $user->role = $data['role'];
        $user->is_active = $data['is_active'];
        $user->district_id = $this->districtFor($data);
        $user->email_verified_at = now();
        $user->save();

        Activity::record('user.created', $user, ['role' => $user->role]);

        return redirect()->route('users.index')->with('success', 'ইউজার তৈরি হয়েছে।');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', ['user' => $user, 'districts' => $this->districts()]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $isSelf = $user->is($request->user());

        // Lock-out protection.
        if ($isSelf && ($data['role'] !== $user->role || ! $data['is_active'])) {
            return back()->withInput()->withErrors(['role' => 'নিজের রোল বদলানো বা নিজেকে নিষ্ক্রিয় করা যাবে না।']);
        }

        if ($this->wouldRemoveLastAdmin($user, $data['role'], $data['is_active'])) {
            return back()->withInput()->withErrors(['role' => 'সর্বশেষ সক্রিয় অ্যাডমিনকে নামানো বা নিষ্ক্রিয় করা যাবে না।']);
        }

        $before = ['role' => $user->role, 'is_active' => $user->is_active, 'district_id' => $user->district_id];

        $user->fill(collect($data)->only(['name', 'email', 'phone', 'bio'])->all());

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->role = $data['role'];
        $user->is_active = $data['is_active'];
        $user->district_id = $this->districtFor($data);
        $user->save();

        Activity::record('user.updated', $user, [
            'before' => $before,
            'after' => ['role' => $user->role, 'is_active' => $user->is_active, 'district_id' => $user->district_id],
            'password_changed' => ! empty($data['password']),
        ]);

        return redirect()->route('users.index')->with('success', 'ইউজার আপডেট হয়েছে।');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'নিজের অ্যাকাউন্ট মুছতে পারবেন না।']);
        }

        if ($this->wouldRemoveLastAdmin($user, null, false)) {
            return back()->withErrors(['user' => 'সর্বশেষ সক্রিয় অ্যাডমিনকে মুছে ফেলা যাবে না।']);
        }

        if ($user->news()->withTrashed()->exists()) {
            return back()->withErrors(['user' => 'এই ইউজারের সংবাদ আছে। মুছে না ফেলে "নিষ্ক্রিয়" করুন।']);
        }

        $user->delete();

        Activity::record('user.deleted', null, ['email' => $user->email, 'name' => $user->name]);

        return redirect()->route('users.index')->with('success', 'ইউজার মুছে ফেলা হয়েছে।');
    }

    private function districts()
    {
        return Location::districts()->with('parent:id,name_bn')->orderBy('name_en')->get(['id', 'parent_id', 'name_bn']);
    }

    /** Only reporters carry a district; any other role must not keep a stale one. */
    private function districtFor(array $data): ?int
    {
        return $data['role'] === User::ROLE_REPORTER ? ($data['district_id'] ?? null) : null;
    }

    /** Would changing $user to ($newRole, $active) leave the system without an active admin? */
    private function wouldRemoveLastAdmin(User $user, ?string $newRole, bool $active): bool
    {
        $isActiveAdminNow = $user->role === User::ROLE_ADMIN && $user->is_active;
        $staysActiveAdmin = $newRole === User::ROLE_ADMIN && $active;

        if (! $isActiveAdminNow || $staysActiveAdmin) {
            return false;
        }

        $otherActiveAdmins = User::where('role', User::ROLE_ADMIN)
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->count();

        return $otherActiveAdmins === 0;
    }
}
