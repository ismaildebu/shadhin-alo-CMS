<?php

namespace App\Models;

use App\Support\EpaperLayout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ই-পেপারের একটি সংস্করণ = একটি তারিখের একটি পাতা।
 * পাতার গঠন (কলাম, প্রস্থ, ব্লক) layout JSON-এ থাকে; বিস্তারিত App\Support\EpaperLayout-এ।
 */
class EpaperPage extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'খসড়া',
        self::STATUS_PUBLISHED => 'প্রকাশিত',
    ];

    protected $fillable = [
        'edition_date',
        'status',
        'layout',
        'created_by',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected function casts(): array
    {
        return [
            'edition_date' => 'date',
            'layout' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), self::STATUS_PUBLISHED);
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc($this->qualifyColumn('edition_date'));
    }

    /** পড়ার উপযোগী গঠন; পুরোনো বা ভাঙা JSON হলেও ডিফল্ট তিন কলাম। */
    public function plan(): array
    {
        return EpaperLayout::read($this->layout);
    }

    /** পাতায় মোট ব্লক (সংবাদ) বসানো হয়েছে। */
    public function blockCount(): int
    {
        return array_sum(array_map(
            fn (array $column) => count($column['blocks']),
            $this->plan()['columns']
        ));
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /** @return array{0: string, 1: string} লেবেল ও ব্যাজের CSS ক্লাস */
    public function state(): array
    {
        return $this->status === self::STATUS_PUBLISHED
            ? ['প্রকাশিত', 'st-published']
            : ['খসড়া', 'st-draft'];
    }
}
