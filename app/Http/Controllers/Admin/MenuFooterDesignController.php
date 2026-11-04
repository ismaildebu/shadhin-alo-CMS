<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\MenuFooterDesign;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MenuFooterDesignController extends Controller
{
    private const HEX = 'regex:/^#[0-9a-fA-F]{6}$/';

    public function edit(): View
    {
        return view('admin.menu-footer-design', [
            'design' => MenuFooterDesign::all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        [$rules, $messages] = self::validation();

        $data = $request->validate($rules, $messages);

        $values = [];

        foreach (array_keys(MenuFooterDesign::LABELS) as $key) {
            $values[$key] = in_array($key, MenuFooterDesign::COLORS, true)
                ? strtolower($data[$key])
                : $data[$key];
        }

        Settings::set($values);

        return back()->with('success', 'মেনু ও ফুটারের ডিজাইন সংরক্ষণ হয়েছে।');
    }

    public function reset(): RedirectResponse
    {
        Settings::set(MenuFooterDesign::DEFAULTS);

        return back()->with('success', 'মেনু ও ফুটারের ডিজাইন ডিফল্টে ফিরে গেছে।');
    }

    /**
     * Rules and messages built from the same whitelists the CSS uses, so a new
     * option can only be saved if MenuFooterDesign can actually render it.
     *
     * @return array{array<string, array<int, string>>, array<string, string>}
     */
    private static function validation(): array
    {
        $rules = [];
        $messages = [];

        foreach (MenuFooterDesign::LABELS as $key => $label) {
            if (in_array($key, MenuFooterDesign::COLORS, true)) {
                $rules[$key] = ['required', self::HEX];
                $messages[$key.'.regex'] = $label.' সঠিক নয় — ৬ অক্ষরের হেক্স কোড দিন।';

                continue;
            }

            if (isset(MenuFooterDesign::CHOICES[$key])) {
                $rules[$key] = ['required', Rule::in(array_keys(MenuFooterDesign::CHOICES[$key]))];
                $messages[$key.'.in'] = $label.' সঠিক নয়।';

                continue;
            }

            $rules[$key] = ['required', 'in:0,1'];
            $messages[$key.'.in'] = $label.' সঠিক নয়।';
        }

        return [$rules, $messages];
    }
}
