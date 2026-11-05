<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit row for one trending search term inspected by the trends subsystem.
 * Every term gets a verdict, so "why is the count low" always has an answer.
 */
class TrendCheck extends Model
{
    public const VERDICT_IMPORTED = 'imported';

    public const VERDICT_DUPLICATE = 'duplicate';

    public const VERDICT_REJECTED = 'rejected';

    public const VERDICT_SUGGESTED = 'suggested';

    protected $fillable = [
        'geo',
        'trend_key',
        'query',
        'approx_traffic',
        'language',
        'candidate_host',
        'candidate_link',
        'published_at',
        'verdict',
        'reason',
        'news_id',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class);
    }
}
