<?php

namespace App\Support\NewsImport;

use App\Models\Category;
use App\Models\Media;
use App\Models\News;
use App\Models\NewsImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Saves the entries found by FeedReader as draft articles.
 *
 * Nothing is published: an editor reviews every draft. The body is the report pulled from
 * the source page when the source supports it, otherwise the feed excerpt; either way the
 * draft always ends with a link back to the source.
 */
final class NewsImporter
{
    /** Normalized titles already known, shared by all items of this run. */
    private static ?array $titleKeys = null;

    /**
     * Read every enabled feed and, unless this is a preview, save new entries.
     *
     * @param  list<string>  $only
     * @return array<string, array{name: string, feed: string, error: ?string, items: list<array<string, mixed>>}>
     */
    public static function run(array $only = [], ?int $limit = null, bool $dryRun = false): array
    {
        self::resetMemo();

        $blocks = FeedReader::collect($only, $limit);

        if ($dryRun) {
            return $blocks;
        }

        foreach ($blocks as &$block) {
            foreach ($block['items'] as &$item) {
                if (($item['state'] ?? '') !== 'new') {
                    continue;
                }

                $result = self::save($item);

                $item['state'] = $result['state'];
                $item['reason'] = $result['reason'];
                $item['news_id'] = $result['news_id'];
            }

            unset($item);
        }

        unset($block);

        return $blocks;
    }

    /**
     * Save one feed item as a draft. Returns the new state and a Bengali explanation.
     *
     * @param  array<string, mixed>  $item
     * @return array{state: string, reason: string, news_id: ?int}
     */
    public static function save(array $item): array
    {
        $config = (array) config('news_import', []);

        $sourceKey = (string) ($item['source'] ?? '');
        $sourceName = (string) ($config['sources'][$sourceKey]['name'] ?? $sourceKey);
        $link = trim((string) ($item['link'] ?? ''));
        $title = trim((string) ($item['title'] ?? ''));

        if ($title === '' || $link === '') {
            return self::failed('শিরোনাম বা লিংক নেই');
        }

        $externalHash = sha1($link);

        if (NewsImport::query()->where('source', $sourceKey)->where('external_hash', $externalHash)->exists()) {
            return self::duplicate('এই লিংক আগেই আনা হয়েছে');
        }

        $titleKey = FeedReader::titleKey($title);
        $known = self::knownTitles($config);

        if (isset($known[$titleKey])) {
            return self::duplicate('একই শিরোনামের সংবাদ আগেই আছে');
        }

        $category = self::resolveCategory($item, $sourceKey, $config);

        if (! $category) {
            return self::failed('ক্যাটাগরি পাওয়া যায়নি (আগে ক্যাটাগরি সিড করুন)');
        }

        $author = self::author($config);
        $content = trim((string) ($item['content'] ?? ''));
        $excerpt = trim((string) ($item['excerpt'] ?? ''));
        $summary = mb_substr($excerpt !== '' ? $excerpt : $title, 0, 1000);

        $description = self::body($content, $excerpt !== '' ? $excerpt : $title)
            .self::sourceLine($link, $sourceName);

        $image = self::importImage($item['image'] ?? null, $author, $config);

        try {
            $news = DB::transaction(function () use ($item, $category, $author, $title, $summary, $description, $image, $sourceKey, $sourceName, $link, $titleKey, $externalHash) {
                $news = News::create([
                    'category_id' => $category->id,
                    'author_id' => $author?->id,
                    'type' => News::TYPE_NEWS,
                    'title' => $title,
                    'slug' => News::uniqueSlug($title),
                    'summary' => $summary,
                    'description' => $description,
                    'image' => $image,
                    'image_credit' => $image ? $sourceName : null,
                    'status' => News::STATUS_DRAFT,
                    'published_at' => $item['published_at'] ? Carbon::parse((string) $item['published_at']) : null,
                ]);

                $news->categories()->sync([$category->id]);

                NewsImport::create([
                    'source' => $sourceKey,
                    'external_hash' => $externalHash,
                    'title_hash' => $titleKey,
                    'link' => $link,
                    'news_id' => $news->id,
                ]);

                return $news;
            });
        } catch (Throwable $e) {
            if ($image !== null) {
                Media::removeFiles($image);
                Media::query()->where('filename', $image)->delete();
            }

            return self::failed('সংরক্ষণ ব্যর্থ: '.$e->getMessage());
        }

        $known[$titleKey] = true;
        self::$titleKeys = $known;

        return ['state' => 'imported', 'reason' => 'খসড়া #'.$news->id, 'news_id' => $news->id];
    }

    /**
     * Re-read the source page for imported articles and replace a short auto-generated
     * body with the full report. Drafts are always eligible; a published article only
     * when its body is still the auto one (it ends with the source line and is short),
     * so an editor's own text is never replaced.
     *
     * @param  list<string>  $only  source keys to refresh; empty = every source
     * @param  bool  $force  re-fetch even when the body already looks full
     * @return array{updated: int, skipped: int, failed: int, rows: list<array<string, mixed>>}
     */
    public static function refresh(array $only = [], bool $force = false): array
    {
        $config = (array) config('news_import', []);
        $result = ['updated' => 0, 'skipped' => 0, 'failed' => 0, 'rows' => []];

        $imports = NewsImport::query()
            ->when($only !== [], fn ($query) => $query->whereIn('source', $only))
            ->orderBy('id')
            ->get();

        foreach ($imports as $import) {
            $source = (array) ($config['sources'][$import->source] ?? []);
            $sourceName = (string) ($source['name'] ?? $import->source);
            $news = News::withTrashed()->find($import->news_id);
            $row = ['id' => $import->news_id, 'title' => '', 'source' => $sourceName, 'state' => '', 'reason' => ''];

            if (! $news) {
                $result['skipped']++;
                $row['state'] = 'skipped';
                $row['reason'] = 'সংবাদ পাওয়া যায়নি';
                $result['rows'][] = $row;
                continue;
            }

            $row['title'] = (string) $news->title;

            if (trim((string) ($source['content']['class'] ?? '')) === '') {
                $result['skipped']++;
                $row['state'] = 'skipped';
                $row['reason'] = 'এই সোর্সের বডি আনা যায় না';
                $result['rows'][] = $row;
                continue;
            }

            $body = (string) $news->description;
            $auto = str_contains($body, 'সূত্র:');
            $isDraft = $news->status === News::STATUS_DRAFT;

            if (! $force && (mb_strlen(strip_tags($body)) >= 1200 || (! $isDraft && ! $auto))) {
                $result['skipped']++;
                $row['state'] = 'skipped';
                $row['reason'] = 'আগেই পূর্ণ বা সম্পাদিত';
                $result['rows'][] = $row;
                continue;
            }

            try {
                $meta = FeedReader::ogMeta((string) $import->link, $config, $source);
            } catch (Throwable $e) {
                $result['failed']++;
                $row['state'] = 'failed';
                $row['reason'] = $e->getMessage();
                $result['rows'][] = $row;
                continue;
            }

            $content = trim((string) ($meta['content'] ?? ''));

            if ($content === '') {
                $result['skipped']++;
                $row['state'] = 'skipped';
                $row['reason'] = 'বডি পাওয়া যায়নি';
                $result['rows'][] = $row;
                continue;
            }

            $news->description = $content.self::sourceLine((string) $import->link, $sourceName);
            $news->save();

            $result['updated']++;
            $row['state'] = 'updated';
            $row['reason'] = mb_strlen(strip_tags($content)).' অক্ষর';
            $result['rows'][] = $row;
        }

        return $result;
    }

    /** Forget the title cache (a new run, or a new test). */
    public static function resetMemo(): void
    {
        self::$titleKeys = null;
    }

    /** Full report when it was fetched, otherwise one paragraph holding the excerpt. */
    private static function body(string $content, string $fallback): string
    {
        return $content !== '' ? $content : '<p>'.e($fallback).'</p>';
    }

    private static function sourceLine(string $link, string $sourceName): string
    {
        return '<p>সূত্র: <a href="'.e($link).'" rel="nofollow noopener" target="_blank">'.e($sourceName).'</a></p>';
    }

    /**
     * Normalized titles of recent articles, including soft-deleted ones.
     *
     * @return array<string, true>
     */
    private static function knownTitles(array $config): array
    {
        if (self::$titleKeys !== null) {
            return self::$titleKeys;
        }

        $days = (int) ($config['collect']['dedupe_window_days'] ?? 30);

        try {
            return self::$titleKeys = News::withTrashed()
                ->where('created_at', '>=', now()->subDays($days))
                ->orderByDesc('id')
                ->limit(3000)
                ->pluck('title')
                ->mapWithKeys(fn ($title) => [FeedReader::titleKey((string) $title) => true])
                ->all();
        } catch (Throwable) {
            return self::$titleKeys = [];
        }
    }

    /**
     * The local category for a feed section. If the mapped slug is missing (an admin
     * renamed it), the source's default, then Latest, then others are tried.
     */
    private static function resolveCategory(array $item, string $sourceKey, array $config): ?Category
    {
        $candidates = array_unique(array_filter([
            (string) ($item['category'] ?? ''),
            (string) ($config['sources'][$sourceKey]['default_category'] ?? ''),
            'Latest',
            'others',
        ]));

        foreach ($candidates as $slug) {
            $category = Category::query()->where('slug', $slug)->first();

            if ($category) {
                return $category;
            }
        }

        return null;
    }

    private static function author(array $config): ?User
    {
        $id = (int) ($config['import']['author_id'] ?? 0);

        return $id > 0 ? User::find($id) : null;
    }

    /**
     * Download, check and register a feed image. Any failure simply leaves the article
     * without an image, so the site's own map/chart fallback is used.
     */
    private static function importImage(mixed $url, ?User $author, array $config): ?string
    {
        if (! is_string($url) || trim($url) === '' || empty($config['images']['download'])) {
            return null;
        }

        if (self::publicImageUrl(trim($url)) === null) {
            return null;
        }

        $maxBytes = (int) ($config['images']['max_bytes'] ?? 5_000_000);
        $minWidth = (int) ($config['images']['min_width'] ?? 480);

        $tmp = null;

        try {
            $bytes = FeedReader::fetchUrl(trim($url), $config, $maxBytes);
            $info = @getimagesizefromstring($bytes);

            if (! $info || $info[0] < $minWidth) {
                return null;
            }

            $tmp = tempnam(sys_get_temp_dir(), 'news-import-');

            if ($tmp === false || file_put_contents($tmp, $bytes) === false) {
                return null;
            }

            $name = basename((string) parse_url(trim($url), PHP_URL_PATH)) ?: 'image';
            $file = new UploadedFile($tmp, $name, $info['mime'] ?? 'image/jpeg', null, true);

            return Media::storeUpload($file, $author)->filename;
        } catch (Throwable) {
            return null;
        } finally {
            if (is_string($tmp)) {
                @unlink($tmp);
            }
        }
    }

    /** Only plain http(s) URLs on public hosts are fetched (no localhost / private ranges). */
    private static function publicImageUrl(string $url): ?string
    {
        if (! preg_match('#^https?://#i', $url)) {
            return null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $public = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

            if ($public === false) {
                return null;
            }
        }

        return $url;
    }

    private static function duplicate(string $reason): array
    {
        return ['state' => 'duplicate', 'reason' => $reason, 'news_id' => null];
    }

    private static function failed(string $reason): array
    {
        return ['state' => 'failed', 'reason' => $reason, 'news_id' => null];
    }
}
