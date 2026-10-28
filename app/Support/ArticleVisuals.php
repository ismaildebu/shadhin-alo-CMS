<?php

namespace App\Support;

/**
 * Turns data shortcodes inside an article body into accessible, script-free visuals.
 *
 * [chart type="bar|column|line|pie|donut" title="..." labels="A|B|C" values="1|2|3" unit="%" source="..."]
 * [stats title="..." items="Label=Value|Label=Value" source="..."]
 * [map title="..." data="ঢাকা=78|চট্টগ্রাম=74" unit="%" source="..."]  (division map, uses App\Support\BdMap)
 *
 * Old shortcodes without "type" keep working (they render as bars). Everything printed is
 * escaped, numbers are validated, and the SVG/HTML is generated here, never taken from input.
 */
final class ArticleVisuals
{
    private const TYPES = ['bar', 'column', 'line', 'pie', 'donut'];

    private const MAX_POINTS = 12;

    private const MAX_SLICES = 8;

    private const MAX_STATS = 6;

    private const DEFAULT_SOURCE = 'সূত্র: প্রতিবেদনে উল্লেখিত তথ্য';

    /** Division map key => Bengali name (the same names the builder page uses). */
    private const LABELS = [
        'barishal' => 'বরিশাল', 'chattogram' => 'চট্টগ্রাম', 'dhaka' => 'ঢাকা', 'khulna' => 'খুলনা',
        'mymensingh' => 'ময়মনসিংহ', 'rajshahi' => 'রাজশাহী', 'rangpur' => 'রংপুর', 'sylhet' => 'সিলেট',
    ];

    /** Accepted Bengali spellings; English names go through BdMap::keyFor(). */
    private const BN_ALIASES = [
        'বরিশাল' => 'barishal', 'চট্টগ্রাম' => 'chattogram', 'চিটাগাং' => 'chattogram', 'চিটাগং' => 'chattogram',
        'ঢাকা' => 'dhaka', 'খুলনা' => 'khulna', 'ময়মনসিংহ' => 'mymensingh', 'ময়মনসিং' => 'mymensingh',
        'রাজশাহী' => 'rajshahi', 'রংপুর' => 'rangpur', 'সিলেট' => 'sylhet',
    ];

    private const DIGITS = [
        '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
        '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
    ];

    public static function expand(string $html): string
    {
        // A shortcode alone in a paragraph: drop the <p> wrapper so the figure stays valid HTML.
        $html = preg_replace('/<p[^>]*>\s*(\[(?:chart|stats|map)\b[^\]]*\])\s*<\/p>/u', '$1', $html) ?? $html;

        return preg_replace_callback(
            '/\[(chart|stats|map)\b([^\]]*)\]/u',
            fn (array $m) => match ($m[1]) {
                'stats' => self::stats(self::attributes($m[2])),
                'map' => self::map(self::attributes($m[2])),
                default => self::chart(self::attributes($m[2])),
            },
            $html
        ) ?? $html;
    }

    /** @return array<string, string> */
    private static function attributes(string $raw): array
    {
        // Rich-text editors may store quotes as entities or curly quotes.
        $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $raw = str_replace(["\u{201C}", "\u{201D}", "\u{201E}"], '"', $raw);
        $raw = str_replace("\u{00A0}", ' ', $raw);

        preg_match_all('/([a-zA-Z_]+)\s*=\s*"([^"]*)"/u', $raw, $found, PREG_SET_ORDER);

        $attributes = [];

        foreach ($found as $pair) {
            $attributes[strtolower($pair[1])] = trim($pair[2]);
        }

        return $attributes;
    }

    /** @param array<string, string> $a */
    private static function chart(array $a): string
    {
        $type = in_array($a['type'] ?? 'bar', self::TYPES, true) ? ($a['type'] ?? 'bar') : 'bar';
        $title = $a['title'] ?? '';
        $unit = mb_substr($a['unit'] ?? '', 0, 12);

        $items = self::points($a['labels'] ?? '', $a['values'] ?? '', $type === 'line');

        if (in_array($type, ['pie', 'donut'], true)) {
            $items = array_values(array_filter($items, fn (array $item) => $item['value'] > 0));
            $items = array_slice($items, 0, self::MAX_SLICES);
        }

        if ($items === []) {
            return '<p class="sa-chart-error">চার্টের তথ্য সঠিক নয়।</p>';
        }

        $plot = match ($type) {
            'column' => self::column($items, $unit, $title),
            'line' => self::line($items, $unit, $title),
            'pie', 'donut' => self::pie($items, $unit, $title, $type === 'donut'),
            default => self::bar($items, $unit, $title),
        };

        return self::figure($type, $title, $plot.self::table($items, $unit), self::source($a));
    }

    /**
     * @return list<array{label: string, value: float}>
     */
    private static function points(string $labelsRaw, string $valuesRaw, bool $allowNegative): array
    {
        $labels = array_map('trim', explode('|', $labelsRaw));
        $values = array_map(
            fn (string $value) => str_replace(',', '', strtr(trim($value), self::DIGITS)),
            explode('|', $valuesRaw)
        );

        $items = [];
        $count = min(count($labels), count($values), self::MAX_POINTS);

        for ($i = 0; $i < $count; $i++) {
            if ($labels[$i] === '' || ! is_numeric($values[$i])) {
                continue;
            }

            $number = (float) $values[$i];

            $items[] = [
                'label' => $labels[$i],
                'value' => $allowNegative ? $number : max(0.0, $number),
            ];
        }

        return $items;
    }

    private static function figure(string $type, string $title, string $inner, string $source): string
    {
        return '<figure class="sa-html-chart sa-visual sa-visual-'.$type.'" aria-label="'.e($title).'">'.
            ($title !== '' ? '<figcaption class="sa-html-chart-title">'.e($title).'</figcaption>' : '').
            $inner.
            '<p class="sa-chart-note">'.e($source).'</p></figure>';
    }

    /** @param array<string, string> $a */
    private static function source(array $a): string
    {
        $source = mb_substr($a['source'] ?? '', 0, 150);

        return $source !== '' ? 'সূত্র: '.$source : self::DEFAULT_SOURCE;
    }

    private static function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    /** @param list<array{label: string, value: float}> $items */
    private static function bar(array $items, string $unit, string $title): string
    {
        $max = max(array_column($items, 'value'));
        $max = $max > 0 ? $max : 1;

        $html = '<div class="sa-chart-bars" role="img" aria-label="'.e($title).'">';

        foreach ($items as $item) {
            $width = max(0, min(100, ($item['value'] / $max) * 100));

            $html .= '<div class="sa-chart-row"><div class="sa-chart-label">'.e($item['label']).
                '</div><div class="sa-chart-track"><span class="sa-chart-bar" style="width:'.
                number_format($width, 2, '.', '').'%"></span></div><div class="sa-chart-value">'.
                e(self::format($item['value']).$unit).'</div></div>';
        }

        return $html.'</div>';
    }

    /** @param list<array{label: string, value: float}> $items */
    private static function column(array $items, string $unit, string $title): string
    {
        $n = count($items);
        $max = max(array_column($items, 'value'));
        $max = $max > 0 ? $max : 1;

        $html = '<div class="sa-scroll"><div class="sa-col-chart" role="img" aria-label="'.e($title).
            '" style="--n:'.$n.';grid-template-columns:repeat('.$n.',minmax(0,1fr))">';

        foreach ($items as $item) {
            $height = max(0, min(100, ($item['value'] / $max) * 100));

            $html .= '<div class="sa-col"><div class="sa-col-track"><span class="sa-col-bar" style="height:'.
                number_format($height, 2, '.', '').'%"><span class="sa-col-value">'.
                e(self::format($item['value']).$unit).'</span></span></div><span class="sa-col-label">'.
                e($item['label']).'</span></div>';
        }

        return $html.'</div></div>';
    }

    /** @param list<array{label: string, value: float}> $items */
    private static function line(array $items, string $unit, string $title): string
    {
        $n = count($items);
        $values = array_column($items, 'value');
        $min = min(0.0, min($values));
        $max = max($values);

        if ($max <= $min) {
            $max = $min + 1;
        }

        $y = fn (float $value): float => 92 - (($value - $min) / ($max - $min)) * 84;
        $x = fn (int $index): float => (($index + 0.5) / $n) * 100;

        $points = [];
        $dots = '';

        foreach ($items as $i => $item) {
            $px = number_format($x($i), 2, '.', '');
            $py = number_format($y($item['value']), 2, '.', '');

            $points[] = $px.','.$py;
            $dots .= '<span class="sa-line-dot" style="left:'.$px.'%;top:'.$py.'%"><span class="sa-line-val">'.
                e(self::format($item['value']).$unit).'</span></span>';
        }

        $grid = '';

        for ($g = 0; $g < 4; $g++) {
            $line = 8 + $g * 28;
            $grid .= '<line class="sa-line-grid" x1="0" x2="100" y1="'.$line.'" y2="'.$line.'"/>';
        }

        if ($min < 0) {
            $zero = number_format($y(0.0), 2, '.', '');
            $grid .= '<line class="sa-line-zero" x1="0" x2="100" y1="'.$zero.'" y2="'.$zero.'"/>';
        }

        $labels = '';

        foreach ($items as $item) {
            $labels .= '<span>'.e($item['label']).'</span>';
        }

        return '<div class="sa-scroll"><div class="sa-line-chart" role="img" aria-label="'.e($title).'" style="--n:'.$n.'">'.
            '<div class="sa-line-plot"><svg class="sa-line-svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true" focusable="false">'.
            $grid.'<polyline class="sa-line-path" points="'.implode(' ', $points).'"/></svg>'.$dots.'</div>'.
            '<div class="sa-line-labels" style="grid-template-columns:repeat('.$n.',minmax(0,1fr))">'.$labels.'</div>'.
            '</div></div>';
    }

    /** @param list<array{label: string, value: float}> $items */
    private static function pie(array $items, string $unit, string $title, bool $donut): string
    {
        $total = array_sum(array_column($items, 'value'));
        $cx = 100;
        $cy = 100;
        $r = 90;
        $angle = -M_PI / 2;
        $shapes = '';
        $legend = '';

        foreach ($items as $i => $item) {
            $fraction = $item['value'] / $total;
            $class = ($i % self::MAX_SLICES) + 1;

            if ($fraction >= 0.9999) {
                $shapes .= '<circle class="sa-fill-'.$class.'" cx="'.$cx.'" cy="'.$cy.'" r="'.$r.'"/>';
            } else {
                $end = $angle + $fraction * 2 * M_PI;
                $x1 = $cx + $r * cos($angle);
                $y1 = $cy + $r * sin($angle);
                $x2 = $cx + $r * cos($end);
                $y2 = $cy + $r * sin($end);
                $large = $fraction > 0.5 ? 1 : 0;

                $shapes .= sprintf(
                    '<path class="sa-fill-%d" d="M%d %d L%.2F %.2F A%d %d 0 %d 1 %.2F %.2F Z"/>',
                    $class, $cx, $cy, $x1, $y1, $r, $r, $large, $x2, $y2
                );

                $angle = $end;
            }

            $legend .= '<li><span class="sa-swatch sa-bg-'.$class.'"></span><span class="sa-legend-label">'.
                e($item['label']).'</span><span class="sa-legend-value">'.
                e(self::format($item['value']).$unit.' ('.self::format(round($fraction * 100, 1)).'%)').'</span></li>';
        }

        if ($donut) {
            $shapes .= '<circle class="sa-donut-hole" cx="'.$cx.'" cy="'.$cy.'" r="52"/>';
        }

        return '<div class="sa-pie-wrap" role="img" aria-label="'.e($title).'">'.
            '<svg class="sa-pie-svg" viewBox="0 0 200 200" aria-hidden="true" focusable="false">'.$shapes.'</svg>'.
            '<ul class="sa-legend">'.$legend.'</ul></div>';
    }

    /** Data table for screen readers and curious readers. */
    private static function table(array $items, string $unit): string
    {
        $rows = '';

        foreach ($items as $item) {
            $rows .= '<tr><td>'.e($item['label']).'</td><td>'.e(self::format($item['value']).$unit).'</td></tr>';
        }

        return '<details class="sa-visual-data"><summary>তথ্য তালিকা দেখুন</summary>'.
            '<table><tbody>'.$rows.'</tbody></table></details>';
    }

    /** @param array<string, string> $a */
    private static function stats(array $a): string
    {
        $cards = '';
        $count = 0;

        foreach (explode('|', $a['items'] ?? '') as $pair) {
            [$label, $value] = array_pad(array_map('trim', explode('=', $pair, 2)), 2, '');

            if ($label === '' || $value === '') {
                continue;
            }

            $cards .= '<div class="sa-stat"><div class="sa-stat-value">'.e($value).
                '</div><div class="sa-stat-label">'.e($label).'</div></div>';

            if (++$count >= self::MAX_STATS) {
                break;
            }
        }

        if ($cards === '') {
            return '<p class="sa-chart-error">তথ্য সঠিক নয়।</p>';
        }

        return self::figure('stats', $a['title'] ?? '', '<div class="sa-stat-grid">'.$cards.'</div>', self::source($a));
    }

    private static function divisionKey(string $name): ?string
    {
        $name = trim(preg_replace('/\s*বিভাগ$/u', '', $name) ?? $name);

        return self::BN_ALIASES[$name] ?? BdMap::keyFor($name, $name);
    }

    /** @param array<string, string> $a */
    private static function map(array $a): string
    {
        $title = $a['title'] ?? '';
        $unit = mb_substr($a['unit'] ?? '', 0, 12);
        $values = [];
        $skipped = [];

        foreach (array_slice(explode('|', $a['data'] ?? ''), 0, 16) as $pair) {
            [$name, $raw] = array_pad(array_map('trim', explode('=', $pair, 2)), 2, '');

            if ($name === '') {
                continue;
            }

            $raw = str_replace(',', '', strtr($raw, self::DIGITS));
            $key = self::divisionKey($name);

            if ($key === null || ! is_numeric($raw)) {
                $skipped[] = $name;

                continue;
            }

            $values[$key] = (float) $raw;
        }

        if ($values === []) {
            return '<p class="sa-chart-error">মানচিত্রের তথ্য সঠিক নয়।</p>';
        }

        $min = min($values);
        $max = max($values);
        $range = $max - $min;
        $bins = [];

        foreach ($values as $key => $value) {
            $bins[$key] = $range > 0 ? min(4, (int) floor((($value - $min) / $range) * 5)) : 2;
        }

        $paths = '';
        $labels = '';

        foreach (BdMap::SHAPES as $key => $shape) {
            $has = isset($values[$key]);
            $text = $has ? self::format($values[$key]).$unit : '';
            $x = number_format($shape['x'], 1, '.', '');
            $y = number_format($shape['y'], 1, '.', '');

            $paths .= '<path class="'.($has ? 'sa-map-'.($bins[$key] + 1) : 'sa-map-none').'" d="'.$shape['d'].'">'.
                '<title>'.e(self::LABELS[$key].($has ? ': '.$text : '')).'</title></path>';

            $labels .= '<text class="sa-map-label'.($has && $bins[$key] >= 3 ? ' sa-map-label-light' : '').'" x="'.$x.'" y="'.$y.'" text-anchor="middle">'.
                '<tspan x="'.$x.'">'.e(self::LABELS[$key]).'</tspan>'.
                ($has ? '<tspan class="sa-map-num" x="'.$x.'" dy="1.3em">'.e($text).'</tspan>' : '').
                '</text>';
        }

        arsort($values);

        $rank = '';

        foreach ($values as $key => $value) {
            $rank .= '<li><span>'.e(self::LABELS[$key]).'</span><b>'.e(self::format($value).$unit).'</b></li>';
        }

        $scale = '<div class="sa-map-scale"><span>'.e(self::format($min).$unit).'</span>'.
            '<i class="sa-mapbg-1"></i><i class="sa-mapbg-2"></i><i class="sa-mapbg-3"></i><i class="sa-mapbg-4"></i><i class="sa-mapbg-5"></i>'.
            '<span>'.e(self::format($max).$unit).'</span></div>';

        $warning = $skipped === []
            ? ''
            : '<p class="sa-chart-error">চিহ্নিত করা যায়নি: '.e(implode(', ', array_slice($skipped, 0, 8))).'</p>';

        $inner = '<div class="sa-map-wrap">'.
            '<svg class="sa-map-svg" viewBox="'.BdMap::viewBox().'" role="img" aria-label="'.e($title).'">'.$paths.$labels.'</svg>'.
            '<div class="sa-map-side">'.$scale.'<ol class="sa-map-rank">'.$rank.'</ol></div></div>'.
            $warning.
            '<p class="sa-chart-note">মানচিত্রটি সরলীকৃত; সীমানা আনুমানিক।</p>';

        return self::figure('map', $title, $inner, self::source($a));
    }
}
