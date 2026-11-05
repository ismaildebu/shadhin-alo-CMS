<?php

namespace App\Support\NewsTrends;

use Illuminate\Support\Carbon;

/**
 * The publish date of an article page, read from the page's own metadata.
 *
 * Only machine-stated dates are accepted — `article:published_time`, the
 * `datePublished` microdata, JSON-LD and an explicit `<time datetime>`. Nothing is
 * guessed from the URL or from "2 hours ago" text, and a page without any of them is
 * reported as having no date. The caller then drops the item: an unprovable date must
 * never become a made-up one.
 */
final class PublishDate
{
    /** Dates in the future, or older than this, are the parser picking up a template value. */
    private const SANITY_YEARS = 3;

    /** Read the publication moment out of a page's own markup. */
    public static function fromHtml(string $html): ?Carbon
    {
        foreach (self::candidates($html) as $raw) {
            $date = self::toCarbon($raw);

            if ($date !== null && self::plausible($date)) {
                return $date;
            }
        }

        return null;
    }

    /**
     * Ordered by how directly the tag states the publication moment.
     *
     * @return list<string>
     */
    public static function candidates(string $html): array
    {
        $found = [];

        if (preg_match('/property=["\']article:published_time["\']\s+content=["\']([^"\']+)/i', $html, $m)) {
            $found[] = $m[1];
        }

        if (preg_match('/itemprop=["\']datePublished["\']\s+(?:content|datetime)=["\']([^"\']+)/i', $html, $m)) {
            $found[] = $m[1];
        }

        // JSON-LD can be repeated or nested in @graph; every occurrence is a candidate
        // and the plausibility check above decides which one is real.
        if (preg_match_all('/"datePublished"\s*:\s*"([^"]+)"/i', $html, $all)) {
            foreach ($all[1] as $raw) {
                $found[] = $raw;
            }
        }

        if (preg_match('/<time[^>]+datetime=["\']([^"\']+)/i', $html, $m)) {
            $found[] = $m[1];
        }

        return $found;
    }

    public static function toCarbon(string $raw): ?Carbon
    {
        $raw = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($raw === '') {
            return null;
        }

        // "2026-11-05T09:41:52.381+06:00" and "2026-11-05" both parse; prose does not.
        if (! preg_match('/^\d{4}-\d{2}-\d{2}([T ]|$)/', $raw)) {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function plausible(Carbon $date): bool
    {
        return $date->lessThanOrEqualTo(now()->addDay())
            && $date->greaterThan(now()->subYears(self::SANITY_YEARS));
    }
}
