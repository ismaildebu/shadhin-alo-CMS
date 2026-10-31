<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        // The super administrator is permanent: only its password can change
        // (done on the password form). Without this guard the model and the
        // database trigger would reject the save with a 500 error.
        if ($request->user()->isSuperAdmin()) {
            return Redirect::route('profile.edit')->withErrors([
                'name' => 'সুপার অ্যাডমিনের নাম, ইমেইল বা অন্য তথ্য বদলানো যায় না। শুধু পাসওয়ার্ড বদলানো যায়।',
            ]);
        }

        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Refuse BEFORE logging out: the super administrator can never be
        // deleted, and the person must stay signed in when we say no.
        if ($request->user()->isSuperAdmin()) {
            return Redirect::back()->withErrors([
                'password' => 'সুপার অ্যাডমিন অ্যাকাউন্ট কখনো মুছে ফেলা যায় না।',
            ], 'userDeletion');
        }

        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}