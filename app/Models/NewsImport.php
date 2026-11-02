<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per article pulled from an external feed, so the same link or headline
 * is never imported twice.
 */
class NewsImport extends Model
{
    protected $fillable = [
        'source',
        'external_hash',
        'title_hash',
        'link',
        'news_id',
    ];

    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class);
    }
}
