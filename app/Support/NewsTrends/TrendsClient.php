<?php

namespace App\Support\NewsTrends;

use App\Support\NewsImport\FeedReader;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

/**
 * Reads the Google Trends "trending searches" feed for one country.
 *
 * The feed is the only Trends surface that is open without an account and it hands over
 * more than keywords: every term carries up to three real news links with their own
 * publisher and thumbnail. Those links are what the rest of this subsystem works on —
 * the keyword alone is never turned into an article.
 */
final class TrendsClient
{
    private const NS = 'https://trends.google.com/trending/rss';

    /**
     * @param  array<string, mixed>  $config  config('news_trends')
     * @return list<array{query: string, traffic: string, stamp: ?int, picture: ?string, items: list<array{title: string, link: string, source: string, image: ?string}>}>
     */
    public static function trending(array $config): array
    {
        $geo = strtoupper(trim((string) ($config['geo'] ?? 'BD'))) ?: 'BD';
        $url = sprintf((string) $config['feed'], $geo);

        $doc = self::parse(FeedReader::fetchUrl(
            $url,
            $config,
            (int) ($config['request']['max_bytes'] ?? 2_000_000)
        ));

        if ($doc === null) {
            throw new RuntimeException('ট্রেন্ড ফিড পড়া যায়নি');
        }

        return self::terms($doc, $config);
    }

    /**
     * The feed's shape turned into terms and their news links; public so a saved fixture
     * can be tested without the network.
     *
     * @param  array<string, mixed>  $config
     * @return list<array{query: string, traffic: string, stamp: ?int, picture: ?string, items: list<array{title: string, link: string, source: string, image: ?string}>}>
     */
    public static function terms(SimpleXMLElement $doc, array $config): array
    {
        $perTrend = max(1, (int) ($config['collect']['news_items_per_trend'] ?? 3));
        $terms = [];

        foreach ($doc->channel->item as $node) {
            $ext = $node->children(self::NS);
            $query = trim((string) $node->title);

            if ($query === '') {
                continue;
            }

            $items = [];

            foreach ($ext->news_item as $news) {
                if (count($items) >= $perTrend) {
                    break;
                }

                $link = trim((string) $news->news_item_url);

                if ($link === '') {
                    continue;
                }

                $items[] = [
                    'title' => trim((string) $news->news_item_title),
                    'link' => $link,
                    'source' => trim((string) $news->news_item_source),
                    'image' => trim((string) $news->news_item_picture) ?: null,
                ];
            }

            $stamp = trim((string) $node->pubDate);

            $terms[] = [
                'query' => $query,
                'traffic' => trim((string) $ext->approx_traffic),
                // Google's own stamp is when the term started trending, not when the
                // report was published; it is kept for ordering only.
                'stamp' => $stamp === '' ? null : (strtotime($stamp) ?: null),
                'picture' => trim((string) $ext->picture) ?: null,
                'items' => $items,
            ];
        }

        return $terms;
    }

    /** Parse a feed body; public so a fixture can be tested without the network. */
    public static function parse(string $xml): ?SimpleXMLElement
    {
        if (trim($xml) === '') {
            return null;
        }

        try {
            $doc = simplexml_load_string($xml);
        } catch (Throwable) {
            return null;
        }

        return $doc instanceof SimpleXMLElement ? $doc : null;
    }

    /** A stable identity for one trending term, used to stop the same term being pulled twice. */
    public static function key(string $query): string
    {
        return sha1(mb_strtolower(trim(preg_replace('/\s+/u', ' ', $query) ?? $query)));
    }
}
