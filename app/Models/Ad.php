<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Ad extends Model
{
    public const DIR = 'uploads/ads';

    public const CACHE_KEY = 'ads.active';

    /** সাইটের বিজ্ঞাপনের জায়গা: স্লট => লেবেল ও প্রস্তাবিত মাপ। */
    public const SLOTS = [
        'header_top'     => ['label' => 'হেডার: লোগোর পাশে (সব পেজ)', 'size' => '970×90'],
        'home_middle'    => ['label' => 'হোমপেজ: প্রথম সারির নিচে', 'size' => '970×90'],
        'sidebar_top'    => ['label' => 'সাইডবার: ওপরে', 'size' => '300×250'],
        'sidebar_middle' => ['label' => 'সাইডবার: মাঝখানে', 'size' => '300×250'],
        'sidebar_bottom' => ['label' => 'সাইডবার: নিচে', 'size' => '300×250'],
        'sidebar_rail'   => ['label' => 'ছোট সাইডবার (হোমের নিচের সারিগুলোর পাশে)', 'size' => '300×250'],
        'article_top'    => ['label' => 'খবরের পেজ: লেখার ওপরে', 'size' => '728×90'],
        'article_bottom' => ['label' => 'খবরের পেজ: লেখার নিচে', 'size' => '728×90'],
    ];

    public const TYPES = [
        'image' => 'ছবির বিজ্ঞাপন',
        'code' => 'কোড (AdSense / HTML)',
    ];

    protected $fillable = [
        'title', 'slot', 'type', 'image', 'link_url', 'code',
        'starts_at', 'ends_at', 'status', 'clicks', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'clicks' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // সাইটে চালু বিজ্ঞাপনের তালিকা ক্যাশ করা থাকে; বদলালে ক্যাশ পরিষ্কার হবে।
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset(self::DIR.'/'.$this->image) : null;
    }

    /** এখন সাইটে দেখানোর উপযুক্ত কিনা: চালু ও সময়সীমার মধ্যে। */
    public function isLive(): bool
    {
        return $this->status
            && (! $this->starts_at || $this->starts_at->lte(now()))
            && (! $this->ends_at || $this->ends_at->gt(now()));
    }

    /** @return array{0: string, 1: string} লেবেল ও ব্যাজের CSS ক্লাস */
    public function state(): array
    {
        if (! $this->status) {
            return ['বন্ধ', 'st-draft'];
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return ['সময় হয়নি', 'st-scheduled'];
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return ['মেয়াদ শেষ', 'st-off'];
        }

        return ['চলছে', 'st-published'];
    }
}