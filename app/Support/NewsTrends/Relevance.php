<?php

namespace App\Support\NewsTrends;

use Illuminate\Support\Str;

/**
 * The yes/no calls in front of an import: is this publisher trusted, is the item worth
 * a desk slot, which section does it belong to, and — when nothing can be imported —
 * whether the term is even something we could report on ourselves.
 *
 * Every answer comes with a Bengali reason, because the admin page shows why an item
 * was left out instead of silently dropping it.
 */
final class Relevance
{
    /** Bangla letters in these ranges make a text Bangla. */
    private const BANGLA = '/[\x{0980}-\x{09FF}]/u';

    private const BANGLA_RATIO = 0.25;

    /**
     * Hosts on the block list never open, even when a parent domain is allowed.
     * Betting and odds sites are promotion dressed as reporting.
     */
    public static function isBlocked(string $host, array $config): bool
    {
        return self::matches($host, (array) ($config['hosts']['block'] ?? []));
    }

    /**
     * Only a trusted news domain is fetched. The list is deliberately closed: an
     * unknown site costs one rejected item, a malicious one would cost much more.
     */
    public static function isAllowed(string $host, array $config): bool
    {
        return ! self::isBlocked($host, $config)
            && self::matches($host, (array) ($config['hosts']['allow'] ?? []));
    }

    /** 'bn' when the text is mostly Bangla script, otherwise 'en'. */
    public static function language(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return 'en';
        }

        $letters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $bangla = 0;

        foreach ($letters as $letter) {
            if (preg_match(self::BANGLA, $letter) === 1) {
                $bangla++;
            }
        }

        return $bangla / max(1, count($letters)) >= self::BANGLA_RATIO ? 'bn' : 'en';
    }

    /**
     * Bangla reports go to the nationwide section — that is the one slug where
     * NewsImporter resolves district and upazila on its own. Foreign sport lands in
     * "খেলা", everything else foreign in "আন্তর্জাতিক".
     */
    public static function category(string $query, string $headline, string $language, array $config): string
    {
        $categories = (array) ($config['categories'] ?? []);

        if ($language === 'bn') {
            return (string) ($categories['bangladesh'] ?? 'saradesh');
        }

        if (self::isSports($query.' '.$headline, $config)) {
            return (string) ($categories['foreign_sports'] ?? 'sports');
        }

        return (string) ($categories['foreign'] ?? 'international');
    }

    public static function isSports(string $text, array $config): bool
    {
        $text = mb_strtolower($text);
        $keywords = array_map(static fn ($word) => Str::lower((string) $word), (array) ($config['sports_keywords'] ?? []));

        foreach ($keywords as $keyword) {
            $keyword = trim((string) $keyword);

            if ($keyword !== '' && str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is the term something the desk could write about at all? A person, place or event
     * name passes; a bare genre word ("football") or a product search is not news.
     */
    public static function reportable(string $query, array $config): bool
    {
        $query = trim($query);

        if (mb_strlen($query) < (int) ($config['collect']['min_title_chars'] ?? 12)) {
            return false;
        }

        // One generic word with no place, name or number attached.
        if (preg_match('/^[\p{L}]+$/u', $query) === 1 && self::language($query) === 'en') {
            return false;
        }

        return true;
    }

    /**
     * Exact host or a subdomain of a listed host — never a look-alike suffix, so
     * "evil-bbc.com" does not pass because "bbc.com" is trusted.
     */
    private static function matches(string $host, array $list): bool
    {
        $host = strtolower(trim($host));

        if ($host === '') {
            return false;
        }

        foreach ($list as $entry) {
            $entry = strtolower(trim((string) $entry));

            if ($entry === '') {
                continue;
            }

            if ($host === $entry || str_ends_with($host, '.'.$entry)) {
                return true;
            }
        }

        return false;
    }
}
