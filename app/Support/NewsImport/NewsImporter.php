<?php

namespace App\Support\NewsImport;

use App\Models\Category;
use App\Models\Location;
use App\Models\Media;
use App\Models\News;
use App\Models\NewsImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
     * @param  array<string, mixed>|null  $group  category group from config; null = every category
     * @return array<string, array{name: string, feed: string, error: ?string, items: list<array<string, mixed>>}>
     */
    public static function run(array $only = [], ?int $limit = null, bool $dryRun = false, ?array $group = null): array
    {
        self::resetMemo();

        $config = (array) config('news_import', []);
        $blocks = FeedReader::collect($only, $limit, $group);

        if ($dryRun) {
            return $blocks;
        }

        $maxPerRun = max(1, (int) ($config['collect']['max_per_run'] ?? 20));
        $imported = 0;

        foreach ($blocks as &$block) {
            foreach ($block['items'] as &$item) {
                if (($item['state'] ?? '') !== 'new') {
                    continue;
                }

                if ($imported >= $maxPerRun) {
                    $item['state'] = 'skipped';
                    $item['reason'] = 'এই চালের সীমা পূর্ণ ('.$maxPerRun.'টি)';
                    $item['news_id'] = null;

                    continue;
                }

                $result = self::save($item);

                $item['state'] = $result['state'];
                $item['reason'] = $result['reason'];
                $item['news_id'] = $result['news_id'];

                if ($result['state'] === 'imported') {
                    $imported++;
                }
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

        $categories = self::resolveCategories($item, $sourceKey, $config);

        if ($categories->isEmpty()) {
            return self::failed('ক্যাটাগরি পাওয়া যায়নি (আগে ক্যাটাগরি সিড করুন)');
        }

        // A feed's first tag is often a flag such as "টপ নিউজ" (others); the legacy column
        // should point at the real section so listings show the story in the right category.
        $category = $categories->first(fn (Category $cat) => $cat->slug !== 'others') ?? $categories->first();
        $nationwide = $categories->contains(fn (Category $cat) => $cat->slug === Category::NATIONWIDE_SLUG);

        $author = self::author($config);
        $content = trim((string) ($item['content'] ?? ''));
        $excerpt = trim((string) ($item['excerpt'] ?? ''));
        $summary = mb_substr($excerpt !== '' ? $excerpt : $title, 0, 1000);

        // সারাদেশ সংবাদে বিভাগ, জেলা ও উপজেলা — তিনটিই শিরোনাম/সারাংশ/বডি থেকে শনাক্ত হতে হবে।
        $location = $nationwide
            ? LocationMatcher::detect($title, trim($excerpt.' '.strip_tags($content)))
            : null;

        if ($nationwide && (! $location || (! $location->isDistrict() && ! $location->isUpazila()))) {
            return self::skipped('সারাদেশ সংবাদে জেলা বা উপজেলা শনাক্ত হয়নি');
        }

        $reporter = $nationwide && $location ? self::reporterFor($location) : null;

        $image = self::importImage($item['image'] ?? null, $author, $config);

        $description = self::body($content, $excerpt !== '' ? $excerpt : $title)
            .self::sourceLine($link, $sourceName);

        try {
            $news = DB::transaction(function () use ($item, $category, $categories, $location, $reporter, $author, $title, $summary, $description, $image, $sourceKey, $sourceName, $link, $titleKey, $externalHash) {
                $news = News::create([
                    'category_id' => $category->id,
                    'location_id' => $location?->id,
                    'author_id' => $author?->id,
                    'type' => News::TYPE_NEWS,
                    'title' => $title,
                    'slug' => News::uniqueSlug($title),
                    'summary' => $summary,
                    'description' => $description,
                    'reporter' => $reporter,
                    'image' => $image,
                    'image_credit' => $image ? $sourceName : null,
                    'status' => News::STATUS_DRAFT,
                    'published_at' => $item['published_at'] ? Carbon::parse((string) $item['published_at']) : null,
                ]);

                $news->categories()->sync($categories->pluck('id')->all());
                $news->syncTagNames(self::tagNames($categories, $location, $item));

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
     * Every local category for an entry: the primary slug plus all additional feed tags.
     * Falls back to the source default, then others.
     */
    private static function resolveCategories(array $item, string $sourceKey, array $config): Collection
    {
        $slugs = array_values(array_unique(array_filter(array_merge(
            [(string) ($item['category'] ?? '')],
            array_map('strval', (array) ($item['categories'] ?? []))
        ), fn (string $slug) => $slug !== '')));

        $found = collect();

        foreach ($slugs as $slug) {
            $category = Category::query()->where('slug', $slug)->first();

            if ($category && ! $found->contains('id', $category->id)) {
                $found->push($category);
            }
        }

        if ($found->isNotEmpty()) {
            return $found;
        }

        foreach (array_unique(array_filter([
            (string) ($config['sources'][$sourceKey]['default_category'] ?? ''),
            'others',
        ])) as $slug) {
            $category = Category::query()->where('slug', $slug)->first();

            if ($category) {
                return collect([$category]);
            }
        }

        return collect();
    }

    /** সারাদেশের বাইলাইন: জেলা শহরের ঘটনা হলে জেলা, উপজেলা শহরের হলে "উপজেলা (জেলা)". */
    private static function reporterFor(Location $location): string
    {
        if ($location->isDistrict()) {
            return 'সংবাদদাতা, '.$location->name_bn;
        }

        $district = $location->parent?->name_bn;

        if ($district && str_ends_with($location->name_bn, 'সদর')) {
            return 'সংবাদদাতা, '.$district;
        }

        return 'সংবাদদাতা, '.$location->name_bn.($district ? ' ('.$district.')' : '');
    }

    /**
     * Tags for an imported draft: the feed's own labels, the detected place and the local
     * categories. Tag::idsForNames drops duplicates, enforces the per-article limit and
     * creates missing tags.
     *
     * @param  Collection<int, Category>  $categories
     * @return list<string>
     */
    private static function tagNames(Collection $categories, ?Location $location, array $item): array
    {
        $names = array_map(fn ($tag) => trim((string) $tag), (array) ($item['tags'] ?? []));

        if ($location) {
            foreach ($location->path()->reverse() as $place) {
                $names[] = (string) $place->name_bn;
            }
        }

        foreach ($categories as $category) {
            $names[] = (string) $category->name;
        }

        return array_values(array_filter($names, fn (string $name) => $name !== ''));
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

    private static function duplicate(string $reason): array
    {
        return ['state' => 'duplicate', 'reason' => $reason, 'news_id' => null];
    }

    private static function skipped(string $reason): array
    {
        return ['state' => 'skipped', 'reason' => $reason, 'news_id' => null];
    }

    private static function failed(string $reason): array
    {
        return ['state' => 'failed', 'reason' => $reason, 'news_id' => null];
    }
}
