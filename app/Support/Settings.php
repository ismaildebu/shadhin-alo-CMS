<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class Settings
{
    public const CACHE_KEY = 'site.settings';

    /** লোগো ও ফেভিকনের ফোল্ডার (public এর ভেতরে) */
    public const DIR = 'uploads/site';

    /** সেটিংয়ের নাম => ডিফল্ট মান। সেভ করা না থাকলে এই মান চলে। */
    public const DEFAULTS = [
        'site_name' => 'স্বাধীন আলো',
        'site_tagline' => 'সত্যের পথে নির্ভীক',
        'logo' => '',
        'favicon' => '',
        'show_date' => '1',

        'footer_about' => 'স্বাধীন আলো একটি নির্ভরযোগ্য, বস্তুনিষ্ঠ ও আধুনিক বাংলা অনলাইন সংবাদমাধ্যম। দেশ-বিদেশের সর্বশেষ সংবাদ দ্রুত ও নির্ভুলভাবে পাঠকের কাছে পৌঁছে দেওয়াই আমাদের লক্ষ্য।',
        'footer_email' => 'info@shadhinalo.com',
        'footer_phone' => '',
        'footer_address' => 'বাংলাদেশ',
        'footer_categories' => '10',
        'footer_links' => '',
        'social_facebook' => '',
        'social_youtube' => '',
        'social_x' => '',
        'social_instagram' => '',
        'footer_copyright' => 'সর্বস্বত্ব সংরক্ষিত',
        'footer_credit' => 'Nexton BD Tech',

        'ticker_show' => '1',
        'ticker_label' => 'ব্রেকিং নিউজ',
        'ticker_count' => '10',
        'ticker_speed' => 'normal',
        'ticker_max_hours' => '0',
        'menu_more_label' => 'অন্যান্য',
        
        'card_template' => 'overlay',
        'card_size' => 'landscape',
        'card_logo' => '1',
        'card_category' => '1',
        'card_date' => '1',
        'card_accent' => '#d60000',
        'card_footer' => '',
        
        ...ArticleDesign::DEFAULTS,
    ];

    private static ?array $memo = null;

    /** সব সেটিং (ডিফল্টসহ)। টেবিল না থাকলে (মাইগ্রেশনের আগে) শুধু ডিফল্ট। */
    public static function all(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        $stored = Cache::get(self::CACHE_KEY);

        if ($stored === null) {
            try {
                $stored = Setting::pluck('value', 'name')->all();
                Cache::forever(self::CACHE_KEY, $stored);
            } catch (Throwable) {
                $stored = [];
            }
        }

        return self::$memo = array_merge(
            self::DEFAULTS,
            array_filter($stored, fn ($value) => $value !== null)
        );
    }

    public static function get(string $key): string
    {
        return (string) (self::all()[$key] ?? '');
    }

    /** @param array<string, string> $values */
    public static function set(array $values): void
    {
        foreach ($values as $name => $value) {
            if (! array_key_exists($name, self::DEFAULTS)) {
                continue;
            }

            Setting::updateOrCreate(['name' => $name], ['value' => (string) $value]);
        }

        Cache::forget(self::CACHE_KEY);
        self::$memo = null;

        FrontendCache::flush();
    }

    /** সেট করা লোগো, না থাকলে আগের ডিফল্ট লোগো। */
    public static function logoUrl(): string
    {
        $logo = self::get('logo');

        return $logo !== '' ? asset(self::DIR.'/'.$logo) : asset('images/logo.png');
    }

    public static function faviconUrl(): ?string
    {
        $icon = self::get('favicon');

        return $icon !== '' ? asset(self::DIR.'/'.$icon) : null;
    }

    /** @return list<array{label: string, url: string}> */
    public static function footerLinks(): array
    {
        $links = [];

        foreach (preg_split('/\r\n|\r|\n/', self::get('footer_links')) as $line) {
            $parts = array_map('trim', explode('|', $line, 2));

            if (count($parts) === 2 && $parts[0] !== '' && preg_match('~^(https?://|/(?!/))\S+$~u', $parts[1])) {
                $links[] = ['label' => $parts[0], 'url' => $parts[1]];
            }
        }

        return $links;
    }
}