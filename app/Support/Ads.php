<?php

namespace App\Support;

use App\Models\Ad;
use Illuminate\Support\Facades\Cache;

final class Ads
{
    /** স্লটের চালু বিজ্ঞাপনগুলোর মধ্য থেকে একটি এলোমেলোভাবে বেছে দেয় (রোটেশন); না থাকলে null। */
    public static function pick(string $slot): ?Ad
    {
        $ads = Cache::remember(Ad::CACHE_KEY, 60, fn () => Ad::where('status', true)->get());

        $live = $ads->where('slot', $slot)->filter->isLive();

        return $live->isEmpty() ? null : $live->random();
    }
}