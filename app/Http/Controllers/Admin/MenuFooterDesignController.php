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
        $data = $request->validate([
            'menu_bg' => ['required', self::HEX],
            'menu_text' => ['required', self::HEX],
            'menu_hover_bg' => ['required', self::HEX],
            'menu_size' => ['required', Rule::in(array_keys(MenuFooterDesign::MENU_SIZES))],
            'menu_align' => ['required', Rule::in(array_keys(MenuFooterDesign::MENU_ALIGNS))],
            'menu_sticky' => ['required', 'in:0,1'],

            'footer_bg' => ['required', self::HEX],
            'footer_text' => ['required', self::HEX],
            'footer_heading' => ['required', self::HEX],
            'footer_accent' => ['required', self::HEX],
            'footer_hover' => ['required', self::HEX],
            'footer_size' => ['required', Rule::in(array_keys(MenuFooterDesign::FOOTER_SIZES))],
        ], [
            'menu_bg.regex' => 'মেনুর ব্যাকগ্রাউন্ড রং সঠিক নয়।',
            'menu_text.regex' => 'মেনুর লেখার রং সঠিক নয়।',
            'menu_hover_bg.regex' => 'মেনুর হোভার রং সঠিক নয়।',
            'menu_size.in' => 'মেনুর লেখার আকার সঠিক নয়।',
            'menu_align.in' => 'মেনুর অবস্থান সঠিক নয়।',
            'menu_sticky.in' => 'স্টিকি মেনুর মান সঠিক নয়।',
            'footer_bg.regex' => 'ফুটারের ব্যাকগ্রাউন্ড রং সঠিক নয়।',
            'footer_text.regex' => 'ফুটারের লেখার রং সঠিক নয়।',
            'footer_heading.regex' => 'ফুটারের শিরোনামের রং সঠিক নয়।',
            'footer_accent.regex' => 'ফুটারের অ্যাকসেন্ট রং সঠিক নয়।',
            'footer_hover.regex' => 'ফুটারের লিংক হোভার রং সঠিক নয়।',
            'footer_size.in' => 'ফুটারের লেখার আকার সঠিক নয়।',
        ]);

        Settings::set([
            'menu_bg' => strtolower($data['menu_bg']),
            'menu_text' => strtolower($data['menu_text']),
            'menu_hover_bg' => strtolower($data['menu_hover_bg']),
            'menu_size' => $data['menu_size'],
            'menu_align' => $data['menu_align'],
            'menu_sticky' => $data['menu_sticky'],

            'footer_bg' => strtolower($data['footer_bg']),
            'footer_text' => strtolower($data['footer_text']),
            'footer_heading' => strtolower($data['footer_heading']),
            'footer_accent' => strtolower($data['footer_accent']),
            'footer_hover' => strtolower($data['footer_hover']),
            'footer_size' => $data['footer_size'],
        ]);

        return back()->with('success', 'মেনু ও ফুটারের ডিজাইন সংরক্ষণ হয়েছে।');
    }
}
