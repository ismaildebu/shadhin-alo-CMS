<?php

namespace App\Models;

use App\Support\FrontendCache;
use Illuminate\Database\Eloquent\Model;

class SidebarWidget extends Model
{
    /**
     * সাইডবারের অংশ: লেবেল, নোট, সংখ্যার সীমা, স্টাইলের তালিকা।
     * 'styled' = true হলে রং ও স্ক্রল উচ্চতার ঘর দেখায়।
     */
    public const META = [
        'ad_top' => [
            'label' => 'বিজ্ঞাপন: সাইডবারের ওপরে',
            'note' => 'বিজ্ঞাপন পেজের "সাইডবার: ওপরে" জায়গার বিজ্ঞাপন',
            'limit' => null,
            'layouts' => null,
            'default_layout' => null,
            'styled' => false,
        ],
        'tabs' => [
            'label' => 'সর্বশেষ ও জনপ্রিয় (ট্যাব)',
            'note' => null,
            'limit' => ['label' => 'প্রতি ট্যাবে খবর', 'min' => 3, 'max' => 15, 'default' => 5],
            'layouts' => [
                'numbered' => 'ক্রমিক নম্বরসহ তালিকা (আগের)',
                'thumbs' => 'ছবিসহ তালিকা, স্ক্রল বক্স',
                'feature' => 'ওপরে বড় ছবি, নিচে ছবিসহ তালিকা',
                'pills' => 'গোল ট্যাব, গোল ছবি',
                'underline' => 'আন্ডারলাইন ট্যাব, শুধু শিরোনাম',
                'compact' => 'ঘন তারিখসহ তালিকা',
            ],
            'default_layout' => 'numbered',
            'styled' => true,
        ],
        'poll' => [
            'label' => 'অনলাইন ভোট',
            'note' => 'সবচেয়ে নতুন চালু ভোটটি দেখায়। প্রশ্ন, অপশন ও ছবি ঠিক করুন অ্যাডমিনের "অনলাইন ভোট" পেজে; এখান থেকে ক্রম, দেখানো/লুকানো, রং ও ডিজাইন বদলান।',
            'limit' => null,
            'layouts' => [
                'cover' => 'ওপরে বড় ছবি (ডিফল্ট)',
                'side' => 'পাশে ছোট ছবি',
                'plain' => 'ছবি ছাড়া পরিষ্কার কার্ড',
            ],
            'default_layout' => 'cover',
            'styled' => false,
            'accent' => true,
        ],
        'ad_middle' => [
            'label' => 'বিজ্ঞাপন: সাইডবারের মাঝখানে',
            'note' => 'বিজ্ঞাপন পেজের "সাইডবার: মাঝখানে" জায়গার বিজ্ঞাপন',
            'limit' => null,
            'layouts' => null,
            'default_layout' => null,
            'styled' => false,
        ],
        'videos' => [
            'label' => 'সাম্প্রতিক ভিডিও',
            'note' => null,
            'limit' => ['label' => 'ভিডিও', 'min' => 1, 'max' => 5, 'default' => 3],
            'layouts' => null,
            'default_layout' => null,
            'styled' => false,
        ],
        'facebook' => [
            'label' => 'ফেসবুক পেজ',
            'note' => 'সাইট সেটিংয়ের ফেসবুক লিংক থেকে আসে',
            'limit' => null,
            'layouts' => null,
            'default_layout' => null,
            'styled' => false,
        ],
        'tags' => [
            'label' => 'জনপ্রিয় ট্যাগ',
            'note' => null,
            'limit' => ['label' => 'ট্যাগ', 'min' => 4, 'max' => 20, 'default' => 12],
            'layouts' => null,
            'default_layout' => null,
            'styled' => false,
        ],
        'ad_bottom' => [
            'label' => 'বিজ্ঞাপন: সাইডবারের নিচে',
            'note' => 'বিজ্ঞাপন পেজের "সাইডবার: নিচে" জায়গার বিজ্ঞাপন',
            'limit' => null,
            'layouts' => null,
            'default_layout' => null,
            'styled' => false,
        ],
    ];

    protected $fillable = ['name', 'visible', 'item_limit', 'serial', 'layout', 'accent', 'box_height'];

    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
            'item_limit' => 'integer',
            'serial' => 'integer',
            'box_height' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => FrontendCache::flush());
        static::deleted(fn () => FrontendCache::flush());
    }
}