<?php

namespace App\Support\NewsTrends;

use App\Models\News;
use App\Models\TrendCheck;
use App\Models\TrendEvent;
use App\Services\TranslationService;
use App\Support\BanglaTime;
use App\Support\NewsImport\NewsImporter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * The run itself: trending terms in, drafts and desk suggestions out.
 *
 * Saving is delegated to the existing NewsImporter::save() on purpose. Duplicate
 * protection, category resolution, the saradesh district rule, images and the closing
 * "সূত্র:" line all behave exactly as they do for the feeds the site already reads —
 * this subsystem only decides *whether* an item deserves to be offered at all.
 *
 * Order of work per term, and the reason kept for every decision:
 *   1. has this term already been imported in the recent window,
 *   2. is the term something a desk can report on,
 *   3. does one of its news links come from a trusted publisher,
 *   4. does that page state its own publish date, and is it inside the freshness window,
 *   5. is there an actual report on the page (not a teaser or a bot-block shell),
 *   6. run the quotas: total per run, foreign per run, Bangla items first.
 * Anything that fails writes a suggestion row instead of a draft.
 */
final class TrendsImporter
{
    private const TAG_TRANSLATED = 'অনুবাদ';

    private const TAG_NEEDS_TRANSLATION = 'অনুবাদ প্রয়োজন';

    /**
     * @return array{rows: list<array<string, mixed>>, imported: int, suggestions: int,
     *               duplicates: int, rejected: int, forecasts: int,
     *               translation_used: int, translation_requests: int, error: ?string}
     */
    public static function run(bool $dryRun = false, ?int $limit = null): array
    {
        $config = (array) config('news_trends', []);
        $collector = (array) ($config['collect'] ?? []);
        $translator = new TranslationService;

        NewsImporter::resetMemo();
        ArticlePage::resetMemo();

        $result = [
            'rows' => [],
            'imported' => 0,
            'suggestions' => 0,
            'duplicates' => 0,
            'rejected' => 0,
            'forecasts' => 0,
            'translation_used' => 0,
            'translation_requests' => 0,
            'error' => null,
        ];

        if (empty($config['enabled'])) {
            $result['error'] = 'আলোচিত সার্চ থেকে সংগ্রহ বন্ধ আছে (config/news_trends.php → enabled)।';

            return $result;
        }

        try {
            $terms = TrendsClient::trending($config);
        } catch (Throwable $e) {
            $result['error'] = 'ট্রেন্ড ফিড আনা যায়নি: '.$e->getMessage();

            return $result;
        }

        $budget = min(count($terms), $limit ?? max(1, (int) ($collector['max_per_run'] ?? 10)));

        // Bangla terms are our own readers' searches, so they are looked at first;
        // inside a language, the busier term wins.
        usort($terms, static function (array $a, array $b): int {
            return [Relevance::language((string) $a['query']) === 'bn' ? 0 : 1, -self::trafficValue((string) $a['traffic'])]
                <=> [Relevance::language((string) $b['query']) === 'bn' ? 0 : 1, -self::trafficValue((string) $b['traffic'])];
        });

        $terms = array_slice($terms, 0, $budget);

        $imported = 0;
        $foreign = 0;

        foreach ($terms as $term) {
            $query = trim((string) $term['query']);
            $key = TrendsClient::key($query);

            $row = [
                'query' => $query,
                'traffic' => (string) ($term['traffic'] ?? ''),
                'language' => Relevance::language($query.' '.self::titlesOf($term)),
                'verdict' => TrendCheck::VERDICT_REJECTED,
                'reason' => '',
                'host' => '',
                'link' => '',
                'title' => '',
                'original_title' => '',
                'original_content' => '',
                'publisher' => '',
                'category' => '',
                'translation_note' => '',
                'published_at' => null,
                'news_id' => null,
                'headlines' => [],
                'translated' => false,
            ];

            if ($imported >= $budget) {
                $row['reason'] = 'এই চালের সীমা পূর্ণ ('.$budget.'টি)';
                $row = self::finish($row, TrendCheck::VERDICT_REJECTED, $key, $term, $config, $dryRun)['row'];
                $result['rejected']++;
                $result['rows'][] = $row;

                continue;
            }

            $decision = self::inspect($key, $term, $row, $config, $translator, $dryRun, $foreign);
            $row = $decision['row'];
            $verdict = (string) $row['verdict'];

            if ($verdict === TrendCheck::VERDICT_IMPORTED) {
                $imported++;

                if ((string) $row['language'] !== 'bn') {
                    $foreign++;
                }

                $result['imported']++;
            } elseif ($verdict === TrendCheck::VERDICT_DUPLICATE) {
                $result['duplicates']++;
            } elseif ($verdict === TrendCheck::VERDICT_SUGGESTED) {
                $result['suggestions']++;
            } else {
                $result['rejected']++;
            }

            $result['rows'][] = $row;
        }

        $result['translation_used'] = $translator->used();
        $result['translation_requests'] = $translator->requests();

        if (! $dryRun) {
            $result['forecasts'] = Forecaster::remember(Forecaster::upcoming($config), $config);
        }

        return $result;
    }

    /**
     * The admin page's "উৎস যাচাই" probe: does this URL behave like a report we may run?
     * Read-only — nothing is stored, so a host can be checked before it goes on the
     * allow list.
     *
     * @return array{host: string, allowed: bool, blocked: bool, status: string, published_at: ?string, body_chars: int, image: bool, reason: string}
     */
    public static function verifySource(string $url, array $config): array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $row = [
            'host' => $host,
            'allowed' => Relevance::isAllowed($host, $config),
            'blocked' => Relevance::isBlocked($host, $config),
            'status' => 'আনা যায়নি',
            'published_at' => null,
            'body_chars' => 0,
            'image' => false,
            'reason' => '',
        ];

        if ($row['blocked']) {
            $row['reason'] = 'বাজি/অডস সাইট — তালিকায় থাকলেও আনা হবে না';

            return $row;
        }

        ArticlePage::resetMemo();

        $page = ArticlePage::read($url, $config);
        $row['body_chars'] = mb_strlen(strip_tags($page['body']));
        $row['image'] = $page['image'] !== null;
        $row['status'] = $row['body_chars'] > 0 ? 'প্রতিবেদন পাঠযোগ্য' : 'পেজ খুলেছে, প্রতিবেদন নেই';

        if ($page['published_at'] !== null) {
            $row['published_at'] = $page['published_at']->toIso8601String();
            $row['reason'] = 'তারিখ পাওয়া গেছে: '.$page['published_at']->format('Y-m-d H:i');
        } else {
            $row['reason'] = 'নিজের পেজে প্রকাশ-তারিখ নেই — এই সাইট আমদানির জন্য চলবে না';
        }

        return $row;
    }

    /**
     * Every gate for one term, in order. Returns the row with its verdict filled in.
     *
     * @param  array<string, mixed>  $row
     * @return array{row: array<string, mixed>}
     */
    private static function inspect(string $key, array $term, array $row, array $config, TranslationService $translator, bool $dryRun, int $foreignSoFar): array
    {
        $collector = (array) ($config['collect'] ?? []);

        if (self::recentlyImported($key, $config)) {
            $row['reason'] = 'এই শব্দের সংবাদ ইতিমধ্যেই আনা হয়েছে';

            return self::finish($row, TrendCheck::VERDICT_DUPLICATE, $key, $term, $config, $dryRun);
        }

        if (! Relevance::reportable((string) $row['query'], $config)) {
            $row['reason'] = 'সাধারণ বা খুব ছোট শব্দ — এর ওপর আলাদা সংবাদ লেখা যায় না';

            return self::suggest($key, $term, $row, $config, $translator, $dryRun);
        }

        if ((string) $row['language'] !== 'bn' && $foreignSoFar >= max(0, (int) ($collector['max_foreign_per_run'] ?? 4))) {
            $row['reason'] = 'বিদেশি আইটেমের চাল-সীমা পূর্ণ ('.$collector['max_foreign_per_run'].'টি)';

            return self::suggest($key, $term, $row, $config, $translator, $dryRun);
        }

        $candidates = self::candidates($term, $config);

        if ($candidates['list'] === []) {
            $row['reason'] = (string) $candidates['reason'];

            return self::suggest($key, $term, $row, $config, $translator, $dryRun);
        }

        $maxAge = max(1, (int) ($collector['max_age_hours'] ?? 24));
        $minBody = max(1, (int) ($collector['min_body_chars'] ?? 400));
        $lastReason = '';

        foreach ($candidates['list'] as $candidate) {
            $row['host'] = (string) $candidate['host'];
            $row['link'] = (string) $candidate['link'];

            $page = ArticlePage::read((string) $candidate['link'], $config);
            $date = $page['published_at'];

            if ($date === null) {
                $lastReason = 'নিজের পেজে প্রকাশ-তারিখ নেই — পুরোনো খবর ফাঁকি দিয়ে আনা হবে না';

                continue;
            }

            if ($date->lessThan(now()->subHours($maxAge))) {
                $lastReason = 'সংবাদ পুরোনো ('.self::ageHint($date).')';

                continue;
            }

            $bodyChars = mb_strlen(strip_tags($page['body']));

            if ($bodyChars < $minBody) {
                $lastReason = 'পূর্ণ প্রতিবেদন পাওয়া যায়নি ('.$bodyChars.' অক্ষর)';

                continue;
            }

            return self::take($key, $term, $row, $candidate, $page, $config, $translator, $dryRun, $date);
        }

        $row['reason'] = $lastReason !== '' ? $lastReason : 'আনোগ্য প্রতিবেদন মেলেনি';

        return self::suggest($key, $term, $row, $config, $translator, $dryRun);
    }

    /**
     * News links of this term that come from a trusted publisher.
     *
     * @return array{list: list<array{title: string, link: string, source: string, image: ?string, host: string}>, reason: string}
     */
    private static function candidates(array $term, array $config): array
    {
        $blocked = [];
        $unknown = [];
        $allowed = [];

        foreach ((array) ($term['items'] ?? []) as $item) {
            $link = trim((string) ($item['link'] ?? ''));
            $host = strtolower((string) parse_url($link, PHP_URL_HOST));

            if ($link === '' || $host === '') {
                continue;
            }

            if (Relevance::isBlocked($host, $config)) {
                $blocked[] = $host;

                continue;
            }

            if (! Relevance::isAllowed($host, $config)) {
                $unknown[] = $host;

                continue;
            }

            $allowed[] = [
                'title' => trim((string) ($item['title'] ?? '')),
                'link' => $link,
                'source' => trim((string) ($item['source'] ?? '')),
                'image' => $item['image'] ?? null,
                'host' => $host,
            ];
        }

        if ($allowed !== []) {
            return ['list' => $allowed, 'reason' => ''];
        }

        $reason = $blocked !== []
            ? 'বাজি/অডস সাইট বাদ দেওয়া হয়েছে — আনোগ্য সংবাদমাধ্যম নেই'
            : ($unknown !== []
                ? 'অনুমোদিত তালিকায় নেই: '.implode(', ', array_slice(array_values(array_unique($unknown)), 0, 3))
                : 'এই শব্দের কোনো সংবাদ-লিংক ফিডে নেই');

        return ['list' => [], 'reason' => $reason];
    }

    /**
     * One candidate passed every gate: build the item and hand it to the existing importer.
     *
     * @param  array<string, mixed>  $row
     * @param  array{title: string, link: string, source: string, image: ?string, host: string}  $candidate
     * @param  array{published_at: ?Carbon, image: ?string, description: string, body: string, host: string}  $page
     * @return array{row: array<string, mixed>}
     */
    private static function take(string $key, array $term, array $row, array $candidate, array $page, array $config, TranslationService $translator, bool $dryRun, Carbon $date): array
    {
        $collector = (array) ($config['collect'] ?? []);
        $language = (string) $row['language'];
        $foreign = $language !== 'bn';
        $translate = ($config['translate']['enabled'] ?? true) !== false;

        $originalTitle = trim((string) ($candidate['title'] !== '' ? $candidate['title'] : $row['query']));
        $title = $originalTitle;
        $needsTranslation = false;
        $notes = [];

        if ($foreign && $translate) {
            $result = $translator->toBangla($originalTitle, 'auto');
            $title = trim((string) $result['text']);
            $needsTranslation = ! $result['ok'];

            if ($result['note'] !== '') {
                $notes[] = 'শিরোনাম: '.$result['note'];
            }
        }

        if (mb_strlen($title) < (int) ($collector['min_title_chars'] ?? 12)) {
            $row['reason'] = 'শিরোনাম খুব ছোট';

            return self::suggest($key, $term, $row, $config, $translator, $dryRun);
        }

        $body = (string) $page['body'];
        $translated = false;

        if ($foreign && $translate && ! $needsTranslation) {
            $paragraphs = $translator->translateParagraphs($body, 'auto');

            if ($paragraphs['translated']) {
                $body = (string) $paragraphs['html'];
                $translated = true;
            } else {
                $needsTranslation = true;
                $notes[] = 'বডি: '.($paragraphs['note'] !== '' ? $paragraphs['note'] : 'অনুবাদ হয়নি');
            }
        }

        $category = Relevance::category((string) $row['query'], $originalTitle, $language, $config);
        $excerpt = trim((string) $page['description']) !== '' ? trim((string) $page['description']) : $title;
        $tags = [$row['query']];

        if ($translated) {
            $tags[] = self::TAG_TRANSLATED;
        }

        $item = [
            'source' => (string) ($config['source_key'] ?? 'news-trends'),
            'title' => $title,
            'link' => (string) $candidate['link'],
            'category' => $category,
            'categories' => [$category],
            'category_tag' => $category,
            'tags' => $tags,
            'district' => '',
            'published_at' => $date->toIso8601String(),
            'excerpt' => Str::limit($excerpt, 300, ''),
            'content' => $body,
            'image' => $page['image'] ?? $candidate['image'],
            'state' => 'new',
            'reason' => '',
        ];

        $row['title'] = $title;
        $row['original_title'] = $originalTitle;
        $row['original_content'] = (string) $page['body'];
        $row['publisher'] = (string) $candidate['source'];
        $row['category'] = (string) $category;
        $row['translated'] = $translated;
        $row['translation_note'] = implode('; ', $notes);
        $row['published_at'] = $date;

        if ($dryRun) {
            $row['verdict'] = TrendCheck::VERDICT_IMPORTED;
            $row['reason'] = 'সংরক্ষণ হবে (প্রিভিউ)';

            return ['row' => $row];
        }

        try {
            $saved = NewsImporter::save($item);
        } catch (Throwable $e) {
            $row['reason'] = 'সংরক্ষণ ব্যর্থ: '.$e->getMessage();

            return self::suggest($key, $term, $row, $config, $translator, $dryRun);
        }

        if ($saved['state'] !== 'imported') {
            // The existing importer's own gates (same link, same title, the saradesh place
            // rule) decide; that answer is reported as it is, not smoothed over.
            $row['reason'] = (string) $saved['reason'];

            return $saved['state'] === 'duplicate'
                ? self::finish($row, TrendCheck::VERDICT_DUPLICATE, $key, $term, $config, $dryRun)
                : self::suggest($key, $term, $row, $config, $translator, $dryRun);
        }

        $row['news_id'] = (int) $saved['news_id'];
        $row['reason'] = 'খসড়া #'.$saved['news_id'];

        self::tidyDraft($row['news_id'], (string) $candidate['source'], (string) $item['source'], $needsTranslation, $translated);

        return self::finish($row, TrendCheck::VERDICT_IMPORTED, $key, $term, $config, $dryRun);
    }

    /**
     * Two touches on the draft after the save, both ours to make: the "সূত্র:" line should
     * name the real publisher rather than this subsystem's key, and a body still in a
     * foreign language gets the "অনুবাদ প্রয়োজন" tag so the desk can filter for it.
     */
    private static function tidyDraft(?int $newsId, string $publisher, string $sourceKey, bool $needsTranslation, bool $translated): void
    {
        $news = $newsId === null ? null : News::find($newsId);

        if ($news === null) {
            return;
        }

        $body = (string) $news->description;
        $needle = '>'.e($sourceKey).'</a></p>';

        if ($publisher !== '' && $publisher !== $sourceKey && str_contains($body, $needle)) {
            $news->description = str_replace($needle, '>'.e($publisher).'</a></p>', $body);
        }

        $names = $needsTranslation ? [self::TAG_NEEDS_TRANSLATION] : [];

        if ($news->description !== $body) {
            $news->save();
        }

        if ($names !== []) {
            $news->syncTagNames(array_values(array_unique(array_merge(
                $news->tags->pluck('name')->all(),
                $names
            ))));
        }
    }

    /**
     * No draft for this term — give the desk three headlines to write from instead.
     *
     * @param  array<string, mixed>  $row
     * @return array{row: array<string, mixed>}
     */
    private static function suggest(string $key, array $term, array $row, array $config, TranslationService $translator, bool $dryRun): array
    {
        $advice = HeadlineAdvisor::for($term, $config, $translator);

        $row['headlines'] = (array) $advice['headlines'];
        $row['translated'] = (bool) $advice['translated'];
        $row['translation_note'] = (string) $advice['note'];

        if (trim((string) $row['reason']) === '') {
            $row['reason'] = 'আনোগ্য প্রতিবেদন মেলেনি';
        }

        $row['reason'] .= ' — পরামর্শ শিরোনাম দেওয়া হয়েছে';

        return self::finish($row, TrendCheck::VERDICT_SUGGESTED, $key, $term, $config, $dryRun);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{row: array<string, mixed>}
     */
    private static function finish(array $row, string $verdict, string $key, array $term, array $config, bool $dryRun): array
    {
        $row['verdict'] = $verdict;

        if (! $dryRun) {
            self::log($key, $term, $row, $config);
        }

        return ['row' => $row];
    }

    /** Has this exact trending term already produced a draft recently? */
    private static function recentlyImported(string $key, array $config): bool
    {
        $hours = max(1, (int) ($config['collect']['dedupe_hours'] ?? 72));

        try {
            return TrendCheck::query()
                ->where('trend_key', $key)
                ->where('verdict', TrendCheck::VERDICT_IMPORTED)
                ->where('created_at', '>=', now()->subHours($hours))
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /** Audit row, plus the desk-facing event when there is one. */
    private static function log(string $key, array $term, array $row, array $config): void
    {
        TrendCheck::create([
            'geo' => strtoupper(trim((string) ($config['geo'] ?? 'BD'))),
            'trend_key' => $key,
            'query' => Str::limit((string) $row['query'], 250, ''),
            'approx_traffic' => (string) $row['traffic'],
            'language' => (string) $row['language'],
            'candidate_host' => Str::limit((string) $row['host'], 118, ''),
            'candidate_link' => (string) $row['link'] !== '' ? (string) $row['link'] : null,
            'published_at' => $row['published_at'] instanceof Carbon ? $row['published_at'] : null,
            'verdict' => (string) $row['verdict'],
            'reason' => Str::limit((string) $row['reason'], 250, ''),
            'news_id' => $row['news_id'],
        ]);

        if ($row['verdict'] === TrendCheck::VERDICT_IMPORTED) {
            TrendEvent::updateOrCreate(
                ['trend_key' => $key, 'kind' => TrendEvent::KIND_IMPORTED],
                [
                    'query' => (string) $row['query'],
                    'status' => TrendEvent::STATUS_PENDING,
                    'language' => (string) $row['language'],
                    'approx_traffic' => (string) $row['traffic'],
                    'title' => Str::limit((string) $row['title'], 490, ''),
                    'source_host' => (string) $row['host'],
                    'source_link' => (string) $row['link'],
                    'original_title' => Str::limit((string) $row['original_title'], 490, ''),
                    'original_content' => (string) $row['original_content'],
                    'translated' => (bool) $row['translated'],
                    'translation_note' => (string) $row['translation_note'],
                    'category_slug' => (string) $row['category'],
                    'occurred_at' => $row['published_at'] instanceof Carbon ? $row['published_at'] : now(),
                    'news_id' => $row['news_id'],
                ]
            );

            return;
        }

        if ($row['verdict'] === TrendCheck::VERDICT_SUGGESTED && $row['headlines'] !== []) {
            TrendEvent::updateOrCreate(
                ['trend_key' => $key, 'kind' => TrendEvent::KIND_SUGGESTION],
                [
                    'query' => (string) $row['query'],
                    'status' => TrendEvent::STATUS_PENDING,
                    'language' => (string) $row['language'],
                    'approx_traffic' => (string) $row['traffic'],
                    'title' => Str::limit((string) $row['query'], 490, ''),
                    'headlines' => json_encode((array) $row['headlines'], JSON_UNESCAPED_UNICODE),
                    'source_host' => (string) $row['host'],
                    'source_link' => (string) $row['link'] !== '' ? (string) $row['link'] : null,
                    'original_title' => Str::limit((string) $row['original_title'], 490, ''),
                    'translated' => (bool) $row['translated'],
                    'translation_note' => (string) $row['translation_note'],
                    'category_slug' => (string) Relevance::category(
                        (string) $row['query'],
                        (string) $row['original_title'],
                        (string) $row['language'],
                        $config
                    ),
                    'occurred_at' => now(),
                ]
            );
        }
    }

    /** "1000+" to a number, for ordering only. */
    private static function trafficValue(string $traffic): int
    {
        return (int) preg_replace('/\D+/', '', $traffic);
    }

    private static function titlesOf(array $term): string
    {
        $titles = [];

        foreach ((array) ($term['items'] ?? []) as $item) {
            $titles[] = (string) ($item['title'] ?? '');
        }

        return implode(' ', $titles);
    }

    private static function ageHint(Carbon $date): string
    {
        return BanglaTime::digits((string) round($date->diffInMinutes(now()) / 60)).' ঘণ্টা আগে ('.BanglaTime::date($date).')';
    }
}
