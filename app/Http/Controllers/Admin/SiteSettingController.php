<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SiteSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.site-settings', ['s' => Settings::all()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'site_name' => ['required', 'string', 'max:80'],
            'site_tagline' => ['nullable', 'string', 'max:120'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico,webp', 'max:512'],
            'show_date' => ['nullable'],

            'footer_about' => ['nullable', 'string', 'max:600'],
            'footer_email' => ['nullable', 'email', 'max:120'],
            'footer_phone' => ['nullable', 'string', 'max:40'],
            'footer_address' => ['nullable', 'string', 'max:200'],
            'footer_categories' => ['required', 'integer', 'min:0', 'max:20'],
            'footer_links' => ['nullable', 'string', 'max:2000'],
            'social_facebook' => ['nullable', 'url:http,https', 'max:300'],
            'social_youtube' => ['nullable', 'url:http,https', 'max:300'],
            'social_x' => ['nullable', 'url:http,https', 'max:300'],
            'social_instagram' => ['nullable', 'url:http,https', 'max:300'],
            'footer_copyright' => ['nullable', 'string', 'max:150'],
            'footer_credit' => ['nullable', 'string', 'max:100'],

            'ticker_show' => ['nullable'],
            'ticker_label' => ['required', 'string', 'max:40'],
            'ticker_count' => ['required', 'integer', 'min:1', 'max:20'],
            'ticker_speed' => ['required', Rule::in(['slow', 'normal', 'fast'])],
            'ticker_max_hours' => ['required', 'integer', 'min:0', 'max:720'],
        ], [
            'site_name.required' => 'সাইটের নাম লিখুন।',
            'logo.image' => 'লোগো একটি ছবি হতে হবে।',
            'logo.mimes' => 'লোগো png, jpg বা webp হতে হবে।',
            'logo.max' => 'লোগো সর্বোচ্চ ১ MB হতে পারবে।',
            'logo.uploaded' => 'লোগো আপলোড ব্যর্থ হয়েছে (ফাইল খুব বড় হতে পারে)।',
            'favicon.mimes' => 'ফেভিকন png, ico বা webp হতে হবে।',
            'favicon.max' => 'ফেভিকন সর্বোচ্চ ৫১২ KB হতে পারবে।',
            'footer_email.email' => 'সঠিক ইমেইল ঠিকানা দিন।',
            'footer_categories.min' => 'ক্যাটাগরির সংখ্যা ০ বা তার বেশি হতে হবে।',
            'footer_categories.max' => 'ফুটারে সর্বোচ্চ ২০টি ক্যাটাগরি দেখানো যাবে।',
            'social_facebook.url' => 'ফেসবুকের সঠিক লিংক দিন (https:// দিয়ে শুরু)।',
            'social_youtube.url' => 'ইউটিউবের সঠিক লিংক দিন (https:// দিয়ে শুরু)।',
            'social_x.url' => 'এক্সের সঠিক লিংক দিন (https:// দিয়ে শুরু)।',
            'social_instagram.url' => 'ইনস্টাগ্রামের সঠিক লিংক দিন (https:// দিয়ে শুরু)।',
            'ticker_label.required' => 'টিকারের লেবেল লিখুন।',
            'ticker_count.min' => 'টিকারে অন্তত ১টি খবর রাখুন।',
            'ticker_count.max' => 'টিকারে সর্বোচ্চ ২০টি খবর রাখা যাবে।',
        ]);

        $current = Settings::all();
        $values = [];

        foreach ([
            'site_name', 'site_tagline', 'footer_about', 'footer_email', 'footer_phone', 'footer_address',
            'social_facebook', 'social_youtube', 'social_x', 'social_instagram',
            'footer_copyright', 'footer_credit', 'ticker_label',
        ] as $key) {
            $values[$key] = trim((string) $request->input($key, ''));
        }

        foreach (['footer_categories', 'ticker_count', 'ticker_max_hours'] as $key) {
            $values[$key] = (string) (int) $request->input($key);
        }

        $values['ticker_speed'] = (string) $request->input('ticker_speed');
        $values['show_date'] = $request->boolean('show_date') ? '1' : '0';
        $values['ticker_show'] = $request->boolean('ticker_show') ? '1' : '0';
        $values['footer_links'] = $this->cleanLinks((string) $request->input('footer_links', ''));

        $values['logo'] = $this->image($request, 'logo', 'remove_logo', $current['logo']);
        $values['favicon'] = $this->image($request, 'favicon', 'remove_favicon', $current['favicon']);

        Settings::set($values);

        return back()->with('success', 'সাইটের সেটিং সংরক্ষণ হয়েছে।');
    }

    /** প্রতি লাইনে "লেবেল | লিংক"; ভুল লাইন থাকলে সেই লাইনের নম্বর বলে দেয়। */
    private function cleanLinks(string $raw): string
    {
        $out = [];

        foreach (preg_split('/\r\n|\r|\n/', $raw) as $index => $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line, 2));

            if (count($parts) !== 2 || $parts[0] === '' || ! preg_match('~^(https?://|/(?!/))\S+$~u', $parts[1])) {
                throw ValidationException::withMessages([
                    'footer_links' => 'ফুটারের লিংকের '.($index + 1).' নম্বর লাইন ঠিক নয়। এভাবে লিখুন: লেবেল | https://example.com অথবা লেবেল | /about',
                ]);
            }

            $out[] = $parts[0].'|'.$parts[1];
        }

        return implode("\n", array_slice($out, 0, 12));
    }

    /** নতুন ছবি দিলে সেটি রাখে ও পুরোনোটি মোছে; "মুছুন" টিক দিলে ফাইল মুছে ডিফল্টে ফেরে। */
    private function image(Request $request, string $field, string $removeField, string $old): string
    {
        if ($request->hasFile($field)) {
            $file = $request->file($field);
            $name = $field.'-'.Str::uuid().'.'.$file->extension();
            $file->move(public_path(Settings::DIR), $name);

            $this->deleteFile($old);

            return $name;
        }

        if ($request->boolean($removeField)) {
            $this->deleteFile($old);

            return '';
        }

        return $old;
    }

    private function deleteFile(string $name): void
    {
        if ($name !== '') {
            File::delete(public_path(Settings::DIR.'/'.basename($name)));
        }
    }
}