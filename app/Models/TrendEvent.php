<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Desk-facing result of a trending search term: the draft that was imported,
 * the headlines to write when no report was usable, or an expected future trend.
 */
class TrendEvent extends Model
{
    public const KIND_IMPORTED = 'imported';

    public const KIND_SUGGESTION = 'suggestion';

    public const KIND_FORECAST = 'forecast';

    public const STATUS_PENDING = 'pending';

    public const STATUS_DONE = 'done';

    public const STATUS_HIDDEN = 'hidden';

    protected $fillable = [
        'trend_key',
        'query',
        'kind',
        'status',
        'language',
        'approx_traffic',
        'title',
        'headlines',
        'source_host',
        'source_link',
        'original_title',
        'original_content',
        'translated',
        'translation_note',
        'category_slug',
        'occurred_at',
        'news_id',
    ];

    protected $casts = [
        'translated' => 'boolean',
        'occurred_at' => 'datetime',
    ];

    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class);
    }

    /** @return list<string> */
    public function headlineList(): array
    {
        $saved = json_decode((string) $this->headlines, true);

        if (! is_array($saved)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($line) => trim((string) $line), $saved), static fn (string $line) => $line !== ''));
    }

    public function isForeign(): bool
    {
        return $this->language !== 'bn';
    }
}
