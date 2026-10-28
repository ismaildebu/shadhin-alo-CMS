<?php

namespace App\Models;

use App\Support\FrontendCache;
use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    public const TYPES = [
        'home' => 'হোম পেজ',
        'videos' => 'ভিডিও গ্যালারি পেজ',
        'link' => 'নিজের লিংক',
    ];

    protected $fillable = ['type', 'label', 'url', 'new_tab', 'visible', 'more', 'serial'];

    protected function casts(): array
    {
        return [
            'new_tab' => 'boolean',
            'visible' => 'boolean',
            'more' => 'boolean',
            'serial' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => FrontendCache::flush());
        static::deleted(fn () => FrontendCache::flush());
    }

    public function href(): string
    {
        return match ($this->type) {
            'home' => route('home'),
            'videos' => route('frontend.videos'),
            default => (string) $this->url,
        };
    }
}