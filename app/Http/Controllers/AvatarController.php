<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Avatars;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AvatarController extends Controller
{
    /** নিজের প্রোফাইলের ছবি */
    public function storeOwn(Request $request): RedirectResponse
    {
        return $this->store($request, $request->user(), 'প্রোফাইল ছবি বদলানো হয়েছে।');
    }

    public function destroyOwn(Request $request): RedirectResponse
    {
        if ($blocked = $this->blockedFor($request->user())) {
            return $blocked;
        }

        Avatars::remove($request->user());

        return back()->with('success', 'প্রোফাইল ছবি মোছা হয়েছে।');
    }

    /** অ্যাডমিনের জন্য: অন্য ইউজারের ছবি (রাউট শুধু অ্যাডমিনের গ্রুপে) */
    public function storeFor(Request $request, User $user): RedirectResponse
    {
        return $this->store($request, $user, 'ইউজারের ছবি বদলানো হয়েছে।');
    }

    public function destroyFor(User $user): RedirectResponse
    {
        if ($blocked = $this->blockedFor($user)) {
            return $blocked;
        }

        Avatars::remove($user);

        return back()->with('success', 'ইউজারের ছবি মোছা হয়েছে।');
    }

    private function store(Request $request, User $user, string $message): RedirectResponse
    {
        // Check first, so no file is written for a change that cannot be saved.
        if ($blocked = $this->blockedFor($user)) {
            return $blocked;
        }

        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'avatar.required' => 'একটি ছবি বেছে নিন।',
            'avatar.image' => 'শুধু ছবি আপলোড করা যাবে।',
            'avatar.mimes' => 'ছবি jpg, png বা webp হতে হবে।',
            'avatar.max' => 'ছবি সর্বোচ্চ ২ MB হতে পারবে।',
            'avatar.uploaded' => 'আপলোড ব্যর্থ হয়েছে (ফাইল খুব বড় হতে পারে)।',
        ]);

        Avatars::save($user, $request->file('avatar'));

        return back()->with('success', $message);
    }

    /**
     * The super administrator is permanent: only its password can change, so
     * its photo cannot be set or removed either (by itself or by an admin).
     */
    private function blockedFor(User $user): ?RedirectResponse
    {
        if (! $user->isSuperAdmin()) {
            return null;
        }

        return back()->withErrors([
            'avatar' => 'সুপার অ্যাডমিনের ছবি বদলানো বা মোছা যায় না। শুধু পাসওয়ার্ড বদলানো যায়।',
        ]);
    }
}