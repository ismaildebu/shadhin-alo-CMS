<?php

namespace App\Models;

use App\Support\FrontendCache;
use App\Support\HomeLayouts;
use Illuminate\Database\Eloquent\Model;

class HomeSection extends Model
{
    /** হোমপেজের সিস্টেম সেকশন: লেবেল, সবসময় পুরো-চওড়া কিনা, সংখ্যার সীমা, স্টাইলের তালিকা। */
    public const META = [
        'hero' => [
            'label' => 'প্রধান সংবাদ (লিড)',
            'fixed_full' => false,
            'limit' => ['label' => 'পাশের/নিচের খবর', 'min' => 0, 'max' => 6, 'default' => 3],
            'layouts' => [
                'classic' => 'বড় লিড, পাশে তালিকা',
                'slider' => 'স্লাইডার',
                'stack' => 'বড় লিড, নিচে কার্ড',
                ...HomeLayouts::HERO_LABELS,
            ],
            'default_layout' => 'classic',
        ],
        'featured' => [
            'label' => 'নির্বাচিত সংবাদ',
            'fixed_full' => false,
            'limit' => ['label' => 'খবর', 'min' => 2, 'max' => 12, 'default' => 4],
            'layouts' => [
                'cards' => 'চার কার্ড (আগের চেহারা)',
                'boxes' => 'বক্স আইটেম',
                'scroll' => 'পাশে সোয়াইপ করা কার্ড',
                'list' => 'ছবিসহ দুই কলামের তালিকা',
                'gallery' => 'ছবির গ্রিড',
                'slider' => 'স্লাইডার',
                'mosaic' => 'ছবির মোজাইক',
                'grid4' => 'চার কার্ড ও বুলেট তালিকা',
                'split' => 'বামে বড় খবর, ডানে তালিকা',
                'headlines' => 'শুধু শিরোনামের তালিকা',
                ...HomeLayouts::FEATURED_LABELS,
            ],
            'default_layout' => 'cards',
        ],
        'videos' => [
            'label' => 'ভিডিও গ্যালারি',
            'fixed_full' => false,
            'limit' => ['label' => 'ভিডিও', 'min' => 2, 'max' => 8, 'default' => 4],
            'layouts' => [
                'lead' => 'বড় ভিডিও, পাশে তালিকা',
                'grid' => 'ভিডিওর গ্রিড',
                'strip' => 'পাশে সোয়াইপ করা সারি',
            ],
            'default_layout' => 'lead',
        ],
        'ad_middle' => [
            'label' => 'বিজ্ঞাপন (হোমের মাঝখানে)',
            'fixed_full' => true,
            'limit' => null,
            'layouts' => null,
            'default_layout' => null,
        ],
        'latest' => [
            'label' => 'সর্বশেষ সংবাদ',
            'fixed_full' => false,
            'limit' => ['label' => 'প্রতি পেজে খবর', 'min' => 5, 'max' => 30, 'default' => 12],
            'layouts' => [
                'rows' => 'ছবি বামে, লেখা ডানে (সারি)',
                'cards' => 'দুই কলামের কার্ড',
                'compact' => 'শুধু শিরোনামের তালিকা',
            ],
            'default_layout' => 'rows',
        ],
    ];

    protected $fillable = ['name', 'visible', 'full_width', 'item_limit', 'serial', 'layout', 'bg_color', 'color', 'show_title'];

    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
            'show_title' => 'boolean',
            'full_width' => 'boolean',
            'item_limit' => 'integer',
            'serial' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => FrontendCache::flush());
        static::deleted(fn () => FrontendCache::flush());
    }
}