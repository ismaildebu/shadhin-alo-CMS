<?php

namespace App\Support;

use App\Models\News;
use Illuminate\Support\Str;

/**
 * ফটোকার্ড: কার্ডের ডেটা, ডিজাইনের তালিকা ও ডিফল্ট।
 *
 * অ্যাডমিনের কার্ড জেনারেটর আর পাঠকের "ফটোকার্ড" বাটন একই নিয়ম মানে, তাই দুই জায়গায়
 * কপি না রেখে সব এখানেই রাখা। ডিজাইন যোগ করলে এখানে ও public/js/admin-photocard.js — দুই জায়গাতেই যোগ করতে হয়।
 */
final class NewsCard
{
    public const DIR = 'uploads/cards';

    /** কার্ডে না-থাকা ডিজাইনের নাম সেটিংয়ে থাকলে যেটিতে ফিরে যায়। */
    public const FALLBACK = 'paper';

    /** কার্ডের ডিজাইন: কী => লেবেল। */
    public const TEMPLATES = [
        'paper' => 'সংবাদ কার্ড: ওপরে ছবি, নিচে শিরোনাম ও সারাংশ',
        'overlay' => 'ছবির ওপর লেখা',
        'split' => 'হালকা পট, উদ্ধৃতি-শিরোনাম, নিচে গোল কোণার ছবি',
        'text' => 'লেখার কার্ড: ছবি ছাড়া রঙের পট ও বিন্দুর জালে শিরোনাম, সারাংশ ও তারিখ',
    ];

    /**
     * কার্ডে যা যা বসবে (admin-photocard.js এর data আর্গুমেন্ট)।
     *
     * @return array<string, mixed>
     */
    public static function data(News $news): array
    {
        return [
            'title' => (string) $news->title,
            'category' => (string) ($news->category->name ?? ''),
            'color' => $news->category?->home_color,
            'date' => $news->display_date->format('d M Y'),
            'dateBn' => BanglaTime::date($news->display_date),
            'image' => $news->image ? '/uploads/news/'.$news->image : null,
            'logo' => self::path(Settings::logoUrl()),
            'emblem' => self::emblem(),
            'siteName' => Settings::get('site_name'),
            'footer' => self::footerText(),
            'excerpt' => self::plain($news->summary ?: $news->description, 280),
        ];
    }

    /**
     * সেটিংয়ে রাখা ডিজাইন (admin-photocard.js এর options আর্গুমেন্ট)।
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $template = Settings::get('card_template');

        return [
            // বাদ দেওয়া ডিজাইন সেটিংয়ে থাকলে ড্রপডাউন প্রথম অপশন দেখাত, আঁকা হতো overlay — তাই ফেরত।
            'template' => isset(self::TEMPLATES[$template]) ? $template : self::FALLBACK,
            'size' => Settings::get('card_size'),
            'logo' => Settings::get('card_logo') === '1',
            'category' => Settings::get('card_category') === '1',
            'date' => Settings::get('card_date') === '1',
            'accent' => Settings::get('card_accent'),
            'footer' => Settings::get('card_footer'),
        ];
    }

    /** কার্ডের নিচে ডানে যে লেখা বসবে: সেটিংয়ের লেখা, না থাকলে সাইটের ডোমেইন। */
    public static function footerText(): string
    {
        $custom = Settings::get('card_footer');

        if ($custom !== '') {
            return $custom;
        }

        return (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?? '');
    }

    /** গোল প্রতীকের জন্য ফেভিকন (সেট করা থাকলে)। */
    public static function emblem(): ?string
    {
        $url = Settings::faviconUrl();

        return $url ? self::path($url) : null;
    }

    /** পূর্ণ ঠিকানা থেকে শুধু পথ (একই উৎসের ছবি, ক্যানভাস নিরাপদ থাকে)। */
    public static function path(string $url): string
    {
        return parse_url($url, PHP_URL_PATH) ?: $url;
    }

    /** HTML থেকে সাধারণ লেখা: ট্যাগ বাদ, সংক্ষিপ্ত করা। */
    public static function plain(?string $html, int $limit): string
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return Str::limit(trim((string) preg_replace('/\s+/u', ' ', $text)), $limit, '…');
    }
}
