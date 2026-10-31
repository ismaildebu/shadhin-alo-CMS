<?php

namespace App\Support;

use App\Models\Location;
use Throwable;

/**
 * District and upazila maps for the "no image" placeholder.
 *
 * Boundary data (database/data/bd-geo): geoBoundaries ADM3 (CC BY 4.0), simplified. A small
 * index holds the names of 64 districts and their upazilas; each district has its own shape file,
 * so a page only reads the districts it shows.
 *
 * Names in the Location table and in the open data are spelled differently in places
 * (Jashore/Jessore, Cumilla/Comilla, "Borhan Sddin"/"Borhanuddin"), so a place is found by
 * its Bengali name first, then its English name, and last by a close, unambiguous match.
 * Anything that cannot be matched with confidence returns null: no map is better than a wrong one.
 */
final class BdGeo
{
    public const DIR = 'database/data/bd-geo';

    private static ?array $index = null;

    /** @var array<int, list<string>> */
    private static array $shapes = [];

    /** @var array<string, ?int> */
    private static array $memo = [];

    /** @var array<int, true> */
    private static array $defsEmitted = [];

    public static function attribution(): string
    {
        return (string) (self::index()['attribution'] ?? '');
    }

    /**
     * @return array{district: int, upazila: ?int, whole: bool}|null
     *         whole = the news is about the district itself (every upazila is highlighted)
     */
    public static function locate(?Location $district, ?Location $upazila): ?array
    {
        if (! $district) {
            return null;
        }

        try {
            $di = self::district($district);

            if ($di === null) {
                return null;
            }

            if (! $upazila) {
                return ['district' => $di, 'upazila' => null, 'whole' => true];
            }

            return ['district' => $di, 'upazila' => self::upazila($di, $upazila), 'whole' => false];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Markup for one small map.
     * "defs" holds the shapes of the district and is returned once per district and request;
     * "svg" draws them with the news item's upazila highlighted.
     *
     * @param  array{district: int, upazila: ?int, whole: bool}  $geo
     * @return array{defs: string, svg: string}|null
     */
    public static function render(array $geo): ?array
    {
        try {
            $di = $geo['district'];
            $district = self::index()['districts'][$di] ?? null;
            $shapes = self::shapes($di);

            if (! $district || $shapes === []) {
                return null;
            }

            $defs = '';

            if (! isset(self::$defsEmitted[$di])) {
                self::$defsEmitted[$di] = true;

                $paths = '';

                foreach ($shapes as $k => $d) {
                    $paths .= '<path id="sa-g'.$di.'-'.$k.'" d="'.self::cleanPath($d).'"/>';
                }

                $defs = '<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><defs>'.$paths.'</defs></svg>';
            }

            // highlighted shapes are drawn last so their outline is on top
            $dim = '';
            $hot = '';

            foreach (array_keys($shapes) as $k) {
                $isHot = $geo['whole'] || $k === $geo['upazila'];
                $use = '<use href="#sa-g'.$di.'-'.$k.'" class="'.($isHot ? 'sa-noimg-hl' : 'sa-noimg-dim').'"/>';

                if ($isHot) {
                    $hot .= $use;
                } else {
                    $dim .= $use;
                }
            }

            $svg = '<svg class="sa-noimg-dist" viewBox="0 0 '.(float) $district['w'].' '.(float) $district['h'].'" preserveAspectRatio="xMinYMid meet" aria-hidden="true" focusable="false">'.$dim.$hot.'</svg>';

            return ['defs' => $defs, 'svg' => $svg];
        } catch (Throwable) {
            return null;
        }
    }

    // ------------------------------------------------------------------ matching

    private static function district(Location $district): ?int
    {
        $key = 'd'.$district->id;

        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }

        $keys = [];

        foreach (self::index()['districts'] as $i => $row) {
            $keys[$i] = self::keysOf($row);
        }

        return self::$memo[$key] = self::pick($district->name_bn, $district->name_en, $keys, 0.8, 0.1);
    }

    private static function upazila(int $districtIndex, Location $upazila): ?int
    {
        $key = 'u'.$upazila->id;

        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }

        $keys = [];

        foreach (self::index()['districts'][$districtIndex]['upazilas'] as $i => $row) {
            $keys[$i] = self::keysOf($row);
        }

        return self::$memo[$key] = self::pick($upazila->name_bn, $upazila->name_en, $keys, 0.75, 0.08);
    }

    /**
     * @param  array<int, array{bn: list<string>, en: list<string>}>  $candidates
     */
    private static function pick(string $bn, string $en, array $candidates, float $floor, float $margin): ?int
    {
        $kb = self::nbn($bn);
        $ke = self::nen($en);

        foreach ($candidates as $i => $keys) {
            if (($kb !== '' && in_array($kb, $keys['bn'], true)) || ($ke !== '' && in_array($ke, $keys['en'], true))) {
                return $i;
            }
        }

        $scores = [];

        foreach ($candidates as $i => $keys) {
            $best = 0.0;

            foreach ($keys['en'] as $candidate) {
                $best = max($best, self::sim($ke, $candidate));
            }

            $scores[$i] = $best;
        }

        arsort($scores);

        $top = array_slice($scores, 0, 2, true);
        $firstIndex = array_key_first($top);
        $first = $top[$firstIndex] ?? 0.0;
        $second = count($top) > 1 ? array_values($top)[1] : 0.0;

        return ($firstIndex !== null && $first >= $floor && ($first - $second) >= $margin) ? $firstIndex : null;
    }

    /** @return array{bn: list<string>, en: list<string>} */
    private static function keysOf(array $row): array
    {
        return [
            'bn' => array_values(array_unique(array_map(self::nbn(...), $row['bn'] ?? []))),
            'en' => array_values(array_unique(array_map(self::nen(...), $row['en'] ?? []))),
        ];
    }

    /** Bengali: drop spaces and marks, fold look-alike letters (ী/ি, ণ/ন, শ/ষ/স ...). */
    private static function nbn(string $s): string
    {
        $s = preg_replace('/[\s\x{200C}\x{200D}]+/u', '', $s) ?? $s;

        return strtr($s, [
            "\u{09DC}" => "\u{09A1}", "\u{09DD}" => "\u{09A2}", "\u{09DF}" => "\u{09AF}",
            "\u{09BC}" => '', "\u{09CD}" => '', "\u{0981}" => '',
            "\u{09C0}" => "\u{09BF}", "\u{09C2}" => "\u{09C1}",
            "\u{09A3}" => "\u{09A8}", "\u{09B6}" => "\u{09B8}", "\u{09B7}" => "\u{09B8}",
            "\u{09CE}" => "\u{09A4}",
        ]);
    }

    /** English: lower case letters only, common spelling variants folded, vowels dropped in long names. */
    private static function nen(string $s): string
    {
        $s = strtolower(str_replace("'", '', $s));
        $s = preg_replace('/\(.*?\)/', '', $s) ?? $s;
        $s = preg_replace('/[^a-z]/', '', $s) ?? $s;
        $s = str_replace(
            ['ee', 'oo', 'ou', 'ph', 'w', 'z', 'y', 'kh', 'gh', 'sh', 'ch'],
            ['i', 'u', 'o', 'f', 'v', 'j', 'i', 'k', 'g', 's', 'c'],
            $s
        );
        $s = preg_replace('/(.)\1+/', '$1', $s) ?? $s;

        return strlen($s) > 4 ? (preg_replace('/[aeiouh]/', '', $s) ?? $s) : $s;
    }

    private static function sim(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }

        return 1 - levenshtein($a, $b) / max(strlen($a), strlen($b));
    }

    // ------------------------------------------------------------------ data

    private static function index(): array
    {
        return self::$index ??= (function (): array {
            $data = json_decode((string) @file_get_contents(base_path(self::DIR.'/index.json')), true);

            return is_array($data) ? $data : ['attribution' => '', 'districts' => []];
        })();
    }

    /** @return list<string> */
    private static function shapes(int $districtIndex): array
    {
        if (! isset(self::$shapes[$districtIndex])) {
            $file = base_path(sprintf('%s/shapes/%02d.json', self::DIR, $districtIndex));
            $data = json_decode((string) @file_get_contents($file), true);

            self::$shapes[$districtIndex] = is_array($data) ? array_values($data) : [];
        }

        return self::$shapes[$districtIndex];
    }

    /** Path data may only contain move/line/close commands and numbers. */
    private static function cleanPath(string $d): string
    {
        return preg_replace('/[^MLZ0-9.\- ]/', '', $d) ?? '';
    }
}
