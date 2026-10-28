<?php

namespace App\Models;

use App\Support\FrontendCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Video extends Model
{
        use SoftDeletes;

        protected $fillable = ['title', 'youtube_id', 'description', 'status', 'published_at', 'created_by', 'video_channel_id']; 

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // হোমপেজের ব্লক ক্যাশ করা থাকে, তাই ভিডিও বদলালে ক্যাশ ফ্লাশ হবে।
        static::saved(fn () => FrontendCache::flush());
        static::deleted(fn () => FrontendCache::flush());
    }

    /** পাবলিকের জন্য দৃশ্যমান: প্রকাশিত অবস্থায় আছে এবং প্রকাশের সময় এসেছে। */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', true)->where('published_at', '<=', now());
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getThumbnailAttribute(): string
    {
        return 'https://i.ytimg.com/vi/'.$this->youtube_id.'/hqdefault.jpg';
    }

    public function getEmbedUrlAttribute(): string
    {
        return 'https://www.youtube-nocookie.com/embed/'.$this->youtube_id.'?rel=0';
    }

    public function getWatchUrlAttribute(): string
    {
        return 'https://www.youtube.com/watch?v='.$this->youtube_id;
    }

    /** ইউটিউব লিংক (watch, youtu.be, shorts, embed, live) বা সরাসরি ১১ অক্ষরের আইডি থেকে আইডি বের করে। */
    public static function extractYoutubeId(string $input): ?string
    {
        $input = trim($input);

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input)) {
            return $input;
        }

        $pattern = '~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/|live/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})~';

        return preg_match($pattern, $input, $m) ? $m[1] : null;
    }
}