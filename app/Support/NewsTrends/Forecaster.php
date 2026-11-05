<?php

namespace App\Support\NewsTrends;

use App\Models\News;
use App\Models\TrendCheck;
use App\Models\TrendEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * The "আগামী ট্রেন্ড" list: what readers are likely to search for next.
 *
 * Nothing here is invented. Two counted sources feed it —
 *   forward-looking stories: our own published reports whose headline already points at
 *   a date that has not passed ("আগামীকাল", "শনিবার", an announced programme), because
 *   those are exactly the events people search for when they arrive;
 *   repeat trends: terms that showed up in several runs of the trending feed and were
 *   never imported, i.e. a subject our readers keep looking for.
 *
 * Each line carries the article or run it came from, so the desk can check it.
 */
final class Forecaster
{
    /**
     * @param  array<string, mixed>  $config
     * @return list<array{query: string, reason: string, source_link: string, mentions: int}>
     */
    public static function upcoming(array $config): array
    {
        $forecast = (array) ($config['forecast'] ?? []);
        $max = max(1, (int) ($forecast['max_items'] ?? 10));

        $merged = collect(self::fromOwnNews($forecast))
            ->merge(self::fromRepeatTrends($forecast))
            ->sortByDesc('mentions');

        // Two stories about the same event produce near-identical phrases, often with the
        // words in a different order; the desk needs one line per event, keeping the
        // better-evidenced one.
        $rows = [];

        foreach ($merged as $row) {
            if (self::alreadyListed($rows, (string) $row['query'])) {
                continue;
            }

            $rows[] = $row;

            if (count($rows) >= $max) {
                break;
            }
        }

        return $rows;
    }

    /** Does the phrase say the same thing as a line already kept, whatever the word order? */
    private static function alreadyListed(array $rows, string $query): bool
    {
        $words = self::words($query);

        if ($words === []) {
            return false;
        }

        foreach ($rows as $row) {
            $other = self::words((string) $row['query']);
            $shared = count(array_intersect($words, $other));

            if ($shared >= max(3, (int) ceil(0.75 * min(count($words), count($other))))) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private static function words(string $text): array
    {
        $words = preg_split('/\s+/u', mb_strtolower(trim($text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique($words));
    }

    /**
     * Persist the list so the admin tab shows the same rows until the next run.
     *
     * @param  list<array{query: string, reason: string, source_link: string, mentions: int}>  $rows
     */
    public static function remember(array $rows, array $config): int
    {
        $stored = 0;
        $kept = [];

        foreach ($rows as $row) {
            $key = TrendsClient::key($row['query']);
            $kept[] = $key;

            TrendEvent::updateOrCreate(
                ['trend_key' => $key, 'kind' => TrendEvent::KIND_FORECAST],
                [
                    'query' => $row['query'],
                    'status' => TrendEvent::STATUS_PENDING,
                    'language' => Relevance::language($row['query']),
                    'title' => Str::limit($row['query'], 160, ''),
                    'headlines' => json_encode([$row['reason']], JSON_UNESCAPED_UNICODE),
                    'source_host' => (string) (parse_url($row['source_link'], PHP_URL_HOST) ?: ''),
                    'source_link' => $row['source_link'],
                    'category_slug' => (string) ($config['categories']['bangladesh'] ?? 'saradesh'),
                    'occurred_at' => now(),
                ]
            );

            $stored++;
        }

        // Everything this run did not count again is off the desk's list — no clock games.
        TrendEvent::query()
            ->where('kind', TrendEvent::KIND_FORECAST)
            ->whereNotIn('trend_key', $kept)
            ->update(['status' => TrendEvent::STATUS_HIDDEN]);

        return $stored;
    }

    /** @return list<array{query: string, reason: string, source_link: string, mentions: int}> */
    private static function fromOwnNews(array $forecast): array
    {
        $markers = array_values(array_filter(array_map(
            static fn ($marker) => preg_quote((string) $marker, '/'),
            (array) ($forecast['markers'] ?? [])
        ), static fn (string $marker) => $marker !== ''));

        if ($markers === []) {
            return [];
        }

        $hours = max(1, (int) ($forecast['lookback_hours'] ?? 48));

        // Whole words only: "বন্ধ" (a strike) must not match inside "প্রতিবন্ধী" (disabled).
        $pattern = '/(?<![\p{L}\p{M}])('.implode('|', $markers).')(?![\p{L}\p{M}])/u';

        try {
            $titles = News::query()
                ->where('status', News::STATUS_PUBLISHED)
                ->where('published_at', '>=', now()->subHours($hours))
                ->orderByDesc('published_at')
                ->limit(600)
                ->get(['id', 'title', 'slug']);
        } catch (Throwable) {
            return [];
        }

        $found = [];

        foreach ($titles as $news) {
            $title = trim((string) $news->title);

            if ($title === '' || preg_match($pattern, $title) !== 1) {
                continue;
            }

            $query = self::phrase($title);

            if ($query === '') {
                continue;
            }

            $key = TrendsClient::key($query);

            if (! isset($found[$key])) {
                $found[$key] = [
                    'query' => $query,
                    'reason' => 'প্রকাশিত সংবাদেই ঘোষণা আছে: '.Str::limit($title, 120, '…'),
                    'source_link' => '/article/'.$news->slug,
                    'mentions' => 0,
                ];
            }

            $found[$key]['mentions']++;
        }

        $minimum = max(1, (int) ($forecast['min_mentions'] ?? 1));

        return array_values(array_filter($found, static fn (array $row) => $row['mentions'] >= $minimum));
    }

    /** @return list<array{query: string, reason: string, source_link: string, mentions: int}> */
    private static function fromRepeatTrends(array $forecast): array
    {
        // A term we already brought in, or that the recent-window check recognised as
        // brought in, is not something readers will start searching for tomorrow.
        $counts = TrendCheck::query()
            ->where('created_at', '>=', now()->subDays(3))
            ->whereNotIn('verdict', [TrendCheck::VERDICT_IMPORTED, TrendCheck::VERDICT_DUPLICATE])
            ->orderByDesc('id')
            ->limit(2000)
            ->get(['query', 'reason', 'verdict'])
            ->groupBy(fn ($row) => TrendsClient::key((string) $row->query))
            ->filter(fn (Collection $group) => $group->count() >= max(2, (int) ($forecast['min_mentions'] ?? 1)));

        $minimum = max(1, (int) ($forecast['min_mentions'] ?? 1));
        $found = [];

        foreach ($counts as $group) {
            $first = $group->first();

            if ($group->count() < $minimum) {
                continue;
            }

            $found[] = [
                'query' => trim((string) $first->query),
                'reason' => 'গত ৩ দিনে '.$group->count().'বার ট্রেন্ড হয়েছে, কোনো প্রতিবেদন মেলেনি'
                    .' (শেষ কারণ: '.($first->reason ?: $first->verdict).')',
                'source_link' => '',
                'mentions' => $group->count(),
            ];
        }

        return $found;
    }

    /** The searchable core of a headline: the clause before its first break. */
    private static function phrase(string $title): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? '');

        foreach ([':', ' – ', ' — ', ' - '] as $break) {
            $part = trim(Str::before($title, $break));

            if (mb_strlen($part) >= 12) {
                $title = $part;

                break;
            }
        }

        // A trailing verb clause is not a search term.
        $title = (string) preg_replace('/(হবে|করা হবে|অনুষ্ঠিত হবে|পালন করা হবে)\s*$/u', '', $title);
        $title = trim(rtrim($title, ',;।'));

        if (mb_strlen($title) < 12) {
            return '';
        }

        return Str::limit($title, 70, '…');
    }
}
