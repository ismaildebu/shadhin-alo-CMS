<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleRevision extends Model
{
    public const KEEP = 50;

    public $timestamps = false;

    /** Content fields that make up a version (status is workflow, not content). */
    public const CONTENT_FIELDS = [
        'category_id', 'location_id', 'type', 'title', 'shoulder', 'sub_title', 'summary', 'seo_title',
        'description', 'reporter', 'image_caption', 'image_credit',
    ];

    protected $fillable = ['news_id', 'user_id', 'snapshot', 'note', 'created_at'];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public static function capture(News $news, ?User $user = null, ?string $note = null): self
    {
        $revision = self::create([
            'news_id' => $news->id,
            'user_id' => $user?->id,
            'snapshot' => $news->only(self::CONTENT_FIELDS) + [
                'status' => $news->status,
                'tags' => $news->tags()->pluck('tags.name')->all(),
            ],
            'note' => $note !== null && $note !== '' ? mb_substr($note, 0, 500) : null,
            'created_at' => now(),
        ]);

        // Keep the history bounded.
        $oldest = self::where('news_id', $news->id)->orderByDesc('id')->skip(self::KEEP)->take(1)->value('id');
        if ($oldest) {
            self::where('news_id', $news->id)->where('id', '<=', $oldest)->delete();
        }

        return $revision;
    }

    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
