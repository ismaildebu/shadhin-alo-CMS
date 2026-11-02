<?php

namespace App\Support\NewsImport;

use App\Models\News;
use App\Support\HtmlSanitizer;
use DOMDocument;
use DOMXPath;
use RuntimeException;
use Throwable;

/**
 * Reads the RSS feeds listed in config/news_import.php and turns each entry into a plain
 * array that the importer or the preview command can use.
 *
 * A feed is never trusted: links must belong to the source's own hosts, old or very short
 * entries are dropped, the section is mapped to one of our category slugs, and a story whose
 * title is already in the database is marked as a duplicate instead of being imported again.
 */
final class FeedReader
{
    public const MEDIA_NS = 'http://search.yahoo.com/mrss/';

    /** @var array<string, array{image: ?string, description: ?string}> */
    private static array $ogMemo = [];

    /**
     * @param  list<string>  $only  source keys to read; empty = every enabled source
     * @return array<string, array{name: string, feed: string, error: ?string, items: list<array<string, mixed>>}>
     */
    public static function collect(array $only = [], ?int $limit = null): array
    {
        $config = (array) config('news_import', []);

        if (empty($config['enabled'])) {
            return [];
        }

        self::$ogMemo = [];
        $titles = self::recentTitles($config);
        $result = [];

        foreach (($config['sources'] ?? []) as $key => $source) {
            if (empty($source['enabled'])) {
                continue;
            }

            if ($only !== [] && ! in_array($key, $only, true)) {
                continue;
            }

            $block = [
                'name' => (string) ($source['name'] ?? $key),
                'feed' => (string) ($source['feed'] ?? ''),
                'error' => null,
                'items' => [],
            ];

            try {
                $xml = self::fetchUrl($block['feed'], $config, (int) ($config['request']['max_bytes'] ?? 6_000_000));
                $block['items'] = self::parse($key, $source, $xml, $config, $titles, $limit);
            } catch (Throwable $e) {
                $block['error'] = $e->getMessage();
            }

            $result[$key] = $block;
        }

        return $result;
    }

    /**
     * Parse one feed body. Public so the preview tests can run without the network.
     *
     * @param  array<string, true>  $titles  normalized titles already in the database
     * @return list<array<string, mixed>>
     */
    public static function parse(string $key, array $source, string $xml, array $config, array $titles = [], ?int $limit = null): array
    {
        $doc = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET | LIBXML_NOERROR);

        if (! $doc || ! isset($doc->channel->item)) {
            throw new RuntimeException('ফিডটি পড়া যায়নি (RSS নয়?)');
        }

        $max = $limit ?? (int) ($config['collect']['per_source_limit'] ?? 8);
        $maxAge = (int) ($config['collect']['max_age_hours'] ?? 36);
        $minTitle = (int) ($config['collect']['min_title_chars'] ?? 12);
        $scanLimit = max($max, $max * (int) ($config['collect']['scan_factor'] ?? 4));
        $excerptChars = (int) ($config['text']['excerpt_chars'] ?? 500);
        $ogImages = ($config['images']['og_lookup'] ?? true) !== false;
        $ogText = ($config['text']['og_lookup'] ?? true) !== false;
        $allowedHosts = array_map('strtolower', (array) ($source['allowed_hosts'] ?? []));
        $skipCategories = (array) ($source['skip_categories'] ?? []);

        $items = [];
        $seen = [];
        $scanned = 0;
        $accepted = 0;

        foreach ($doc->channel->item as $node) {
            if ($accepted >= $max || $scanned >= $scanLimit) {
                break;
            }

            $scanned++;

            $title = self::text((string) $node->title);

            if ($title === '') {
                continue;
            }

            $link = trim((string) $node->link);
            $host = strtolower((string) parse_url($link, PHP_URL_HOST));
            [$tag, $category] = self::categoryFor($source, (string) $node->category, $link);

            $stamp = strtotime(trim((string) $node->pubDate)) ?: null;
            $excerpt = self::excerpt($node, $excerptChars);
            $image = self::feedImage($node, $source, $link, $config);

            $state = 'new';
            $reason = '';

            // Cheap checks first: nothing is downloaded for an entry that will not be used.
            if ($link === '' || ! in_array($host, $allowedHosts, true)) {
                [$state, $reason] = ['skipped', 'অন্য সাইট'];
            } elseif (in_array($tag, $skipCategories, true)) {
                [$state, $reason] = ['skipped', 'বাদ দেওয়া বিভাগ'];
            } elseif ($stamp !== null && (time() - $stamp) > $maxAge * 3600) {
                [$state, $reason] = ['old', 'পুরোনো'];
            } elseif (mb_strlen($title) < $minTitle) {
                [$state, $reason] = ['skipped', 'শিরোনাম খুব ছোট'];
            } else {
                $titleKey = self::titleKey($title);

                if (isset($titles[$titleKey]) || isset($seen[$titleKey])) {
                    [$state, $reason] = ['duplicate', 'আগেই আছে'];
                } else {
                    $seen[$titleKey] = true;
                }
            }

            $content = '';

            if ($state === 'new') {
                $needsText = $excerpt === '' || $excerpt === $title;
                $needsImage = $image === null && ! empty($source['image']['og']) && $ogImages;
                $needsBody = ($config['text']['full_article'] ?? true) !== false
                    && trim((string) ($source['content']['class'] ?? '')) !== '';

                // A feed sometimes repeats the headline as the summary, and Banglanews24 always does.
                // The article's own og:description fills that gap; the same page also has a bigger
                // image and the full report, so one download serves all three.
                if ($needsBody || ($needsText && $ogText) || $needsImage) {
                    try {
                        $meta = self::ogMeta($link, $config, $source);

                        if ($needsImage) {
                            $image = $meta['image'];
                        }

                        if ($needsText && ($meta['description'] ?? '') !== '') {
                            $excerpt = self::clip(self::text((string) $meta['description']), $excerptChars);
                        }

                        $content = (string) ($meta['content'] ?? '');
                    } catch (Throwable) {
                        // An unreachable article page leaves the title-only summary; the import still works.
                    }
                }

                $accepted++;
            }

            $items[] = [
                'source' => $key,
                'title' => $title,
                'link' => $link,
                'category' => $category,
                'category_tag' => $tag,
                'published_at' => $stamp ? date('c', $stamp) : null,
                'excerpt' => $excerpt,
                'content' => $content,
                'image' => $image,
                'state' => $state,
                'reason' => $reason,
            ];
        }

        return $items;
    }

    /**
     * The source section and the local category slug it maps to.
     * Tag sources read <category>; URL sources take the first path segment of the link.
     *
     * @return array{0: string, 1: string}
     */
    public static function categoryFor(array $source, string $rawTag, string $link): array
    {
        $raw = trim($rawTag);

        if (($source['category_from'] ?? 'tag') === 'url') {
            $path = trim((string) parse_url($link, PHP_URL_PATH), '/');
            $raw = explode('/', $path)[0] ?? '';
        }

        $needle = mb_strtolower($raw);

        foreach ((array) ($source['category_map'] ?? []) as $from => $to) {
            if (mb_strtolower((string) $from) === $needle) {
                return [$raw, (string) $to];
            }
        }

        return [$raw, (string) ($source['default_category'] ?? 'others')];
    }

    /** Bengali-friendly title comparison key: case, punctuation, symbols and spaces are ignored. */
    public static function titleKey(string $title): string
    {
        $key = mb_strtolower(trim($title));
        $key = preg_replace('/[\s\p{P}\p{S}]+/u', '', $key) ?? $key;

        return sha1($key);
    }

    /**
     * Everything taken from the article page itself: og:image, og:description and the full
     * report. Remembered per URL so one entry costs a single download.
     *
     * @return array{image: ?string, description: ?string, content: string}
     */
    public static function ogMeta(string $url, array $config, array $source = []): array
    {
        if (isset(self::$ogMemo[$url])) {
            return self::$ogMemo[$url];
        }

        $html = self::fetchUrl($url, $config, (int) ($config['request']['article_max_bytes'] ?? 8_000_000));

        $image = self::metaContent($html, ['og:image:secure_url', 'og:image:url', 'og:image', 'twitter:image', 'twitter:image:src']);
        $description = self::metaContent($html, ['og:description', 'twitter:description', 'description']);
        $content = ($config['text']['full_article'] ?? true) !== false
            ? self::articleContent($html, $source, (int) ($config['text']['full_article_max_chars'] ?? 30_000), $url)
            : '';

        return self::$ogMemo[$url] = [
            'image' => $image ? self::absUrl($image, $url) : null,
            'description' => $description,
            'content' => $content,
        ];
    }

    /**
     * Pull the report out of the article page and keep only clean, readable HTML.
     *
     * The container is chosen per source in config (`content.class`); headers, hero images,
     * tags and scripts inside it are dropped. Paragraphs, sub-headings, quotes and lists are
     * kept as-is, links are made absolute, and the result is passed through the site's own
     * sanitizer. Blocks stop being added once $maxChars would be exceeded, so no tag is cut.
     */
    public static function articleContent(string $html, array $source, int $maxChars = 30_000, string $baseUrl = ''): string
    {
        $class = trim((string) ($source['content']['class'] ?? ''));

        if ($class === '' || trim($html) === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $loaded = $doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return '';
        }

        $xpath = new DOMXPath($doc);
        $root = $xpath->query('//*['.self::classCondition($class).']')->item(0);

        if ($root === null) {
            return '';
        }

        $drop = array_merge(
            (array) ($source['content']['exclude'] ?? []),
            ['script', 'style', 'noscript', 'iframe', 'form'],
        );

        foreach ($drop as $needle) {
            $needle = trim((string) $needle);

            if ($needle === '') {
                continue;
            }

            $query = in_array($needle, ['script', 'style', 'noscript', 'iframe', 'form'], true)
                ? './/'.$needle
                : './/*['.self::classCondition($needle).']';

            foreach ($xpath->query($query, $root) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        if ($baseUrl !== '') {
            foreach ($xpath->query('.//a[@href]', $root) as $link) {
                $link->setAttribute('href', self::absUrl(trim($link->getAttribute('href')), $baseUrl));
            }
        }

        $parts = [];
        $length = 0;

        foreach ($xpath->query('.//p|.//h2|.//h3|.//h4|.//blockquote|.//li', $root) as $node) {
            if (self::isNestedBlock($node, $root)) {
                continue;
            }

            $chunk = trim((string) $doc->saveHTML($node));

            if ($chunk === '') {
                continue;
            }

            if ($length > 0 && $length + mb_strlen($chunk) > $maxChars) {
                break;
            }

            $parts[] = $chunk;
            $length += mb_strlen($chunk);
        }

        return HtmlSanitizer::clean(implode("\n", $parts));
    }

    /** Fetch a URL through cURL with the timeouts and size cap from the config. */
    public static function fetchUrl(string $url, array $config, ?int $maxBytes = null): string
    {
        if (! preg_match('#^https?://#i', $url)) {
            throw new RuntimeException('ঠিকানা সঠিক নয়');
        }

        $request = (array) ($config['request'] ?? []);
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => (int) ($request['connect_timeout'] ?? 8),
            CURLOPT_TIMEOUT => (int) ($request['timeout'] ?? 20),
            CURLOPT_USERAGENT => (string) ($request['user_agent'] ?? 'NewsBot/1.0'),
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (! is_string($body)) {
            throw new RuntimeException('আনা যায়নি: '.($error !== '' ? $error : 'অজানা ত্রুটি'));
        }

        if ($code < 200 || $code >= 300) {
            throw new RuntimeException("আনা যায়নি (HTTP {$code})");
        }

        if ($maxBytes !== null && strlen($body) > $maxBytes) {
            throw new RuntimeException('ফাইলটি খুব বড়');
        }

        return $body;
    }

    // ------------------------------------------------------------------ internals

    /** Normalized titles of the last N days, including soft-deleted articles. */
    private static function recentTitles(array $config): array
    {
        try {
            return News::withTrashed()
                ->where('created_at', '>=', now()->subDays((int) ($config['collect']['dedupe_window_days'] ?? 30)))
                ->orderByDesc('id')
                ->limit(3000)
                ->pluck('title')
                ->mapWithKeys(fn ($title) => [self::titleKey((string) $title) => true])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /** Cleaned feed summary: entities decoded, tags removed, whitespace collapsed, clipped. */
    private static function excerpt(\SimpleXMLElement $node, int $chars): string
    {
        $excerpt = self::text((string) $node->description);

        if ($excerpt === '' && isset($node->children('http://purl.org/rss/1.0/modules/content/')->encoded)) {
            $excerpt = self::text((string) $node->children('http://purl.org/rss/1.0/modules/content/')->encoded);
        }

        return self::clip($excerpt, $chars);
    }

    /** First usable image carried by the feed itself. */
    private static function feedImage(\SimpleXMLElement $node, array $source, string $link, array $config): ?string
    {
        $media = $node->children(self::MEDIA_NS);
        $from = (array) ($source['image']['from'] ?? ['media_content', 'enclosure', 'media_thumbnail']);

        foreach ($from as $kind) {
            $url = match ($kind) {
                'media_content' => isset($media->content) ? self::attribute($media->content, 'url') : '',
                'media_thumbnail' => isset($media->thumbnail) ? self::attribute($media->thumbnail, 'url') : '',
                'enclosure' => isset($node->enclosure)
                    && str_starts_with(strtolower(self::attribute($node->enclosure, 'type', 'image/')), 'image/')
                        ? self::attribute($node->enclosure, 'url')
                        : '',
                default => '',
            };

            if (trim($url) !== '') {
                return self::absUrl(trim($url), $link);
            }
        }

        return null;
    }

    /** XPath test for an exact class token inside a space-separated class list. */
    private static function classCondition(string $class): string
    {
        $class = str_replace(['"', "'"], '', $class);

        return 'contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")';
    }

    /** True when a block sits inside another block we already keep (p inside blockquote, li inside p...). */
    private static function isNestedBlock(\DOMNode $node, \DOMNode $root): bool
    {
        for ($parent = $node->parentNode; $parent !== null && $parent !== $root; $parent = $parent->parentNode) {
            if (in_array(strtolower($parent->nodeName), ['p', 'h2', 'h3', 'h4', 'blockquote', 'li'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Read an XML attribute. Namespaced elements (media:content) ignore array access
     * on some PHP builds, so attributes() is used instead.
     */
    private static function attribute(\SimpleXMLElement $element, string $name, string $default = ''): string
    {
        $attributes = $element->attributes();

        return $attributes !== null && isset($attributes[$name])
            ? trim((string) $attributes[$name])
            : $default;
    }

    private static function metaContent(string $html, array $keys): ?string
    {
        $alternatives = implode('|', array_map(fn ($key) => preg_quote($key, '/'), $keys));

        $patterns = [
            '/<meta\b[^>]*\b(?:property|name)\s*=\s*["\'](?:'.$alternatives.')["\'][^>]*\bcontent\s*=\s*["\']([^"\']*)["\'][^>]*>/i',
            '/<meta\b[^>]*\bcontent\s*=\s*["\']([^"\']*)["\'][^>]*\b(?:property|name)\s*=\s*["\'](?:'.$alternatives.')["\'][^>]*>/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $match)) {
                return html_entity_decode(trim($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return null;
    }

    private static function absUrl(string $url, string $base): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }

        if (preg_match('#^(https?://|mailto:|tel:)#i', $url)) {
            return $url;
        }

        if (! preg_match('#^https?://#i', $base)) {
            return $url;
        }

        $parts = parse_url($base);
        $root = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        if (str_starts_with($url, '/')) {
            return $root.$url;
        }

        if (str_starts_with($url, '#')) {
            return $base.$url;
        }

        $path = (string) ($parts['path'] ?? '/');

        if (str_starts_with($url, '?')) {
            return $root.$path.$url;
        }

        $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');

        return $root.$dir.'/'.$url;
    }

    private static function text(string $raw): string
    {
        $text = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private static function clip(string $text, int $chars): string
    {
        if ($chars < 1 || mb_strlen($text) <= $chars) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $chars)).'…';
    }
}
