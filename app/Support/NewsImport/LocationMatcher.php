<?php

namespace App\Support\NewsImport;

use App\Models\Location;

/**
 * Guesses the place a সারাদেশ report is about from the names written in its title or summary.
 *
 * Only a name that actually appears is used, and the most specific level wins: an upazila
 * named in the text beats the district, which beats the division. The result is a suggestion
 * on a draft — the editor still sees and completes the three selects.
 */
final class LocationMatcher
{
    /** @var array<string, list<Location>>|null */
    private static ?array $places = null;

    public static function detect(string $title, string $body = ''): ?Location
    {
        $places = self::places();
        $texts = array_values(array_filter([
            self::normalize($title),
            self::normalize(mb_substr($body, 0, 2000)),
        ]));

        foreach ([Location::UPAZILA, Location::DISTRICT, Location::DIVISION] as $type) {
            foreach ($texts as $text) {
                $hit = self::best($places, $type, $text);

                if ($hit) {
                    return $hit;
                }
            }
        }

        return null;
    }

    /** Forget the cached tree (a new run, or a new test). */
    public static function reset(): void
    {
        self::$places = null;
    }

    /**
     * @param  array<string, list<Location>>  $places
     */
    private static function best(array $places, string $type, string $text): ?Location
    {
        $hits = [];

        foreach ($places[$type] ?? [] as $place) {
            if (mb_strpos($text, $place->name_key) !== false) {
                $hits[] = $place;
            }
        }

        if ($hits === []) {
            return null;
        }

        $longest = max(array_map(fn (Location $place) => mb_strlen($place->name_key), $hits));
        $top = array_values(array_filter($hits, fn (Location $place) => mb_strlen($place->name_key) === $longest));

        if (count($top) === 1) {
            return $top[0];
        }

        // A repeated upazila name (e.g. "সদর" names) is settled by the district named in the same text.
        $districtIds = [];

        foreach ($places[Location::DISTRICT] ?? [] as $district) {
            if (mb_strpos($text, $district->name_key) !== false) {
                $districtIds[$district->id] = true;
            }
        }

        foreach ($top as $place) {
            if (isset($districtIds[$place->parent_id])) {
                return $place;
            }
        }

        return null;
    }

    /**
     * @return array<string, list<Location>>
     */
    private static function places(): array
    {
        if (self::$places !== null) {
            return self::$places;
        }

        $grouped = [];

        foreach (Location::query()->get(['id', 'parent_id', 'type', 'name_bn']) as $place) {
            $place->name_key = self::normalize((string) $place->name_bn);
            $grouped[(string) $place->type][] = $place;
        }

        return self::$places = $grouped;
    }

    /** Lowercase, and turn punctuation and symbols into spaces so a name never hides inside one. */
    private static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));

        return trim(preg_replace('/[^\p{L}\p{M}\p{N}]+/u', ' ', $text) ?? $text);
    }
}
