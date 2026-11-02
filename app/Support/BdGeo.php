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

    private static ?array $outlines = null;

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

            $defs = self::upazilaDefs($di);

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

    /** Upazila shapes of one district, defined once per request. */
    public static function upazilaDefs(int $districtIndex): string
    {
        if (isset(self::$defsEmitted[$districtIndex])) {
            return '';
        }

        self::$defsEmitted[$districtIndex] = true;

        $paths = '';

        foreach (self::shapes($districtIndex) as $k => $d) {
            $paths .= '<path id="sa-g'.$districtIndex.'-'.$k.'" d="'.self::cleanPath($d).'"/>';
        }

        // clip so the upazila lines never spill outside the district outline
        $clip = '';
        $district = self::index()['districts'][$districtIndex] ?? null;
        $outline = self::outline($districtIndex);

        if ($district && $outline) {
            [$x0, $y0, $bw, $bh] = array_map(floatval(...), $outline['box']);
            $w = (float) $district['w'];
            $h = (float) $district['h'];

            if ($bw > 0 && $bh > 0) {
                $clip = '<clipPath id="sa-gclip'.$districtIndex.'" clipPathUnits="userSpaceOnUse"><path d="'.self::cleanPath($outline['d']).'" transform="scale('.($w / $bw).' '.($h / $bh).') translate('.-$x0.' '.-$y0.')"/></clipPath>';
            }
        }

        return '<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><defs>'.$paths.$clip.'</defs></svg>';
    }

    /**
     * Geometry for the full-bleed map picture used on Saradesh news without a photo or chart:
     * the country on the left, the news district enlarged on the right, a pin and the district name.
     *
     * @param  array{district: int, upazila: ?int, whole: bool}  $geo
     * @return array<string, mixed>|null
     */
    public static function scene(array $geo): ?array
    {
        try {
            $di = (int) $geo['district'];
            $district = self::index()['districts'][$di] ?? null;
            $outline = self::outline($di);
            $shapes = self::shapes($di);

            if (! $district || ! $outline || $shapes === []) {
                return null;
            }

            $w = (float) $district['w'];
            $h = (float) $district['h'];
            [$x0, $y0, $bw, $bh] = array_map(floatval(...), $outline['box']);
            [$cx, $cy] = array_map(floatval(...), $outline['c']);

            if ($w <= 0 || $h <= 0 || $bw <= 0 || $bh <= 0) {
                return null;
            }

            $left = ['x' => 68.0, 'y' => 226.0, 'w' => 206.0, 'h' => 287.0];
            $max = ['x' => 520.0, 'y' => 10.0, 'w' => 380.0, 'h' => 548.0];

            $scale = min($max['w'] / $bw, $max['h'] / $bh);
            $rw = $bw * $scale;
            $rh = $bh * $scale;
            $rx = $max['x'] + ($max['w'] - $rw) / 2;
            $ry = $max['y'] + ($max['h'] - $rh) / 2;

            $pinX = $rx + $rw / 2;
            $pinTipY = $ry + $rh * 0.46;
            $labelY = min(max($ry + $rh * 0.60, $pinTipY + 38.0), 542.0);

            $dotX = $left['x'] + $cx / 340.0 * $left['w'];
            $dotY = $left['y'] + $cy / 473.0 * $left['h'];

            $elbowY = max($left['y'] + 18.0, $pinTipY - 80.0);
            $elbowX = min(max($left['x'] + $left['w'] + 45.0, $pinX - 225.0), $pinX - 40.0);
            $r = 16.0;

            $connector = sprintf(
                'M%.1f %.1fL%.1f %.1fQ%.1f %.1f %.1f %.1fL%.1f %.1fQ%.1f %.1f %.1f %.1fL%.1f %.1f',
                $dotX, $dotY,
                $dotX, $elbowY + $r,
                $dotX, $elbowY, $dotX + $r, $elbowY,
                $elbowX - $r, $elbowY,
                $elbowX, $elbowY, $elbowX + $r * 0.72, $elbowY + $r * 0.72,
                $pinX, $pinTipY
            );

            $name = (string) ($district['bn'][0] ?? $district['en'][0] ?? '');
            $length = mb_strlen($name);
            $font = $length <= 9 ? 46.0 : ($length <= 13 ? 40.0 : 34.0);

            // fit the same district into the square list thumbnail
            $tScale = min(240.0 / $w, 240.0 / $h);
            $tw = $w * $tScale;
            $th = $h * $tScale;

            return [
                'viewBox' => '0 0 1020 568',
                'index' => $di,
                'name' => $name,
                'left' => $left,
                'right' => ['x' => round($rx, 1), 'y' => round($ry, 1), 'w' => round($rw, 1), 'h' => round($rh, 1)],
                'local' => [$w, $h],
                'box' => [$x0, $y0, $bw, $bh],
                'outline' => $outline['d'],
                'shapes' => array_values($shapes),
                'dot' => ['x' => round($dotX, 1), 'y' => round($dotY, 1)],
                'pin' => ['x' => round($pinX, 1), 'y' => round($pinTipY, 1)],
                'label' => ['x' => round($pinX, 1), 'y' => round($labelY, 1), 'size' => $font],
                'connector' => $connector,
                'stroke' => [
                    'outline' => round(2.6 * $bw / $rw, 3),
                    'inner' => round(0.9 * $w / $rw, 3),
                ],
                'thumb' => [
                    'viewBox' => '0 0 300 300',
                    'x' => round((300.0 - $tw) / 2, 1),
                    'y' => round((300.0 - $th) / 2, 1),
                    'w' => round($tw, 1),
                    'h' => round($th, 1),
                    'pin' => [
                        'x' => round((300.0 - $tw) / 2 + $tw / 2, 1),
                        'y' => round((300.0 - $th) / 2 + $th * 0.5, 1),
                    ],
                    'stroke' => [
                        'outline' => round(2.0 * $bw / $tw, 3),
                        'inner' => round(0.7 * $w / $tw, 3),
                    ],
                ],
                'defs' => self::upazilaDefs($di),
            ];
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

    /** District outline (geoBoundaries ADM2, same 340 x 473 space as App\Support\BdMap). */
    public static function outline(int $districtIndex): ?array
    {
        $row = self::outlines()[$districtIndex] ?? null;

        return is_array($row) && isset($row['d'], $row['box'], $row['c']) ? $row : null;
    }

    private static function outlines(): array
    {
        return self::$outlines ??= (function (): array {
            $data = json_decode((string) @file_get_contents(base_path(self::DIR.'/outlines.json')), true);

            return is_array($data) && is_array($data['districts'] ?? null) ? $data['districts'] : [];
        })();
    }

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
