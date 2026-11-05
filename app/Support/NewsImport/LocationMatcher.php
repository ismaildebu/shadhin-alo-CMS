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

    /** @var array<string, ?Location> */
    private static array $districts = [];

    /**
     * Foreign-desk stories. Even a district's own archive carries them now and then, and no
     * country or world leader puts a report in any Khulna upazila. Used only when the
     * headline itself names no place, so a border or প্রবাসী story that says where it
     * happened is never dropped for mentioning a foreign country.
     */
    private const FOREIGN_DESK = '/(ইরান|ইসরায়েল|ফিলিস্তিন|গাজা|আফগান|তালেবান|রাশিয়া|ইউক্রেন|আমেরিক|মার্কিন|যুক্তরাষ্ট্র|ওয়াশিংটন|ব্রিটেন|ইংল্যান্ড|যুক্তরাজ্য|লন্ডন|ফ্রান্স|জার্মানি|ইউরোপ|তুরস্ক|সৌদি|ইয়েমেন|দুবাই|কাতার|ইমেরাত|আরব আমিরাত|চীনা|চীনের|চীনে|জাপান|কোরিয়া|অস্ট্রেলিয়া|কানাডা|ব্রাজিল|আফ্রিকা|কঙ্গো|লিবিয়া|সিরিয়া|ইরাক|মিয়ানমার|রোহিঙ্গা|জাতিসংঘ|নাটো|ট্রাম্প|বাইডেন|নেতানিয়াহু|পুতিন)/u';

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
        self::$districts = [];
    }

    /**
     * The place a report is about, given what the source's feed claims.
     *
     * A district feed knows its own district, so a headline that names no place still gets
     * one: of the reports in such feeds, 25% name no place at all and are otherwise the
     * district's own news. The claim is only a fallback, though — three things overrule it:
     * the headline names a place (যশোরের ফিডে "সাভার-আশুলিয়ার শিল্পাঞ্চল..." সাভারের সংবাদ,
     * যশোরের নয়), unless that headline also names the claimed district (খুলনার মামলার আসামি
     * "ঢাকায় গ্রেপ্তার" stays খুলনার সংবাদ) or the place is one of its own upazilas; and a
     * foreign-desk story gets no district at all, because even a district's own archive
     * carries the occasional report from abroad. A place that appears only in the body is
     * not strong enough to overrule the feed, since reports mention other districts in
     * passing.
     *
     * @param  string  $body  summary and report text together
     */
    public static function resolve(string $title, string $body, ?Location $declared): ?Location
    {
        if ($declared === null) {
            return self::detect($title, $body);
        }

        $inTitle = self::detect($title);
        $named = $inTitle !== null && ($inTitle->isDistrict() || $inTitle->isUpazila());

        if ($named) {
            if ($inTitle->isUpazila() && (int) $inTitle->parent_id === (int) $declared->id) {
                return $inTitle;
            }

            return str_contains(self::normalize($title), self::normalize((string) $declared->name_bn))
                ? $declared
                : $inTitle;
        }

        return preg_match(self::FOREIGN_DESK, $title.' '.$body) === 1 ? null : $declared;
    }

    /**
     * A district named by the source itself — a site with one feed per district knows which
     * district its reports come from, even when the headline forgets to say so.
     */
    public static function district(string $slug): ?Location
    {
        $slug = trim($slug);

        if ($slug === '') {
            return null;
        }

        if (! array_key_exists($slug, self::$districts)) {
            self::$districts[$slug] = Location::query()
                ->where('type', Location::DISTRICT)
                ->where('slug', $slug)
                ->first();
        }

        return self::$districts[$slug];
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
