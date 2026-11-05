<?php

namespace App\Support\NewsTrends;

use App\Support\HtmlSanitizer;
use App\Support\NewsImport\FeedReader;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * One publisher page, read once: the report text, its image and its stated date.
 *
 * The article pages behind the trending feed come from many different sites, so this
 * uses a deliberately plain extractor — visible `<p>` paragraphs of the page, nothing
 * else. Chrome, navigation, captions and link lists are dropped, and every paragraph is
 * re-escaped, so the draft can never carry markup from someone else's site.
 *
 * A page that is bot-blocked reads as a few hundred bytes of shell and yields no
 * paragraphs; the caller treats that as "no report" and the item is not imported.
 */
final class ArticlePage
{
    /** @var array<string, array<string, mixed>> per-URL memo, so one link costs one download */
    private static array $memo = [];

    /**
     * @param  array<string, mixed>  $config  config('news_trends')
     * @return array{published_at: ?Carbon, image: ?string, description: string, body: string, host: string}
     */
    public static function read(string $url, array $config): array
    {
        $url = trim($url);

        if (isset(self::$memo[$url])) {
            return self::$memo[$url];
        }

        $host = (string) parse_url($url, PHP_URL_HOST);
        $html = '';

        try {
            $html = FeedReader::fetchUrl(
                $url,
                $config,
                (int) ($config['request']['article_max_bytes'] ?? 6_000_000),
                $host === '' ? [] : [strtolower($host)]
            );
        } catch (Throwable) {
            // An unreachable page is reported as an empty page; the caller explains why.
        }

        $maxChars = (int) ($config['collect']['body_max_chars'] ?? 20_000);

        return self::$memo[$url] = [
            'published_at' => PublishDate::fromHtml($html),
            'image' => self::absolute(self::meta($html, ['og:image:secure_url', 'og:image:url', 'og:image', 'twitter:image']), $url),
            'description' => self::meta($html, ['og:description', 'twitter:description', 'description']) ?? '',
            'body' => self::paragraphs($html, $maxChars),
            'host' => strtolower($host),
        ];
    }

    /** Forget downloaded pages (a new run, or a new test). */
    public static function resetMemo(): void
    {
        self::$memo = [];
    }

    /**
     * The page's own paragraphs, as clean HTML.
     *
     * @return string empty when the page holds no readable report
     */
    public static function paragraphs(string $html, int $maxChars = 20_000): string
    {
        if (trim($html) === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $loaded = $doc->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return '';
        }

        $xpath = new DOMXPath($doc);

        // Anything that is not the report itself goes before the paragraphs are read.
        foreach (['script', 'style', 'noscript', 'svg', 'form', 'nav', 'header', 'footer', 'aside', 'figure', 'figcaption', 'button'] as $tag) {
            foreach (iterator_to_array($xpath->query('//'.$tag)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $parts = [];
        $used = 0;

        foreach ($xpath->query('//p') as $paragraph) {
            $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode($paragraph->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');

            // One-line teasers and image captions are not paragraphs of a report.
            if (mb_strlen($text) < 45 || Str::startsWith(mb_strtolower($text), 'advertise')) {
                continue;
            }

            $used += mb_strlen($text);

            if ($used > $maxChars) {
                break;
            }

            $parts[] = '<p>'.e($text).'</p>';
        }

        return HtmlSanitizer::clean(implode("\n", $parts));
    }

    private static function meta(string $html, array $keys): ?string
    {
        foreach ($keys as $key) {
            $pattern = '/<meta[^>]+(?:property|name)=["\']'.preg_quote($key, '/').'"["\'][^>]+content=["\']([^"\']+)["\']|'
                .'<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:property|name)=["\']'.preg_quote($key, '/').'"["\']/i';

            if (preg_match($pattern, $html, $m)) {
                $value = trim(html_entity_decode($m[1] !== '' ? $m[1] : $m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private static function absolute(?string $url, string $base): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return (string) (parse_url($base, PHP_URL_SCHEME) ?: 'https').':'.$url;
        }

        $root = preg_replace('#^(https?://[^/]+).*#', '$1', $base);

        return $root.(str_starts_with($url, '/') ? '' : '/').ltrim($url, '/');
    }
}
