<?php

namespace App\Support;

/**
 * Turns data shortcodes inside an article body into accessible, script-free visuals.
 *
 * [chart type="bar|column|line|pie|donut|grouped|3d" title="..." labels="A|B|C" values="1|2|3" unit="%" source="..."]
 * [chart type="grouped" title="..." labels="A|B|C" series="Fig1=1,2,3|Fig2=4,5,6"]
 * [chart id="5"]  (a saved chart created in Admin > গ্রাফ ও চার্ট)
 * [stats title="..." items="Label=Value|Label=Value" source="..."]
 * [map title="..." data="ঢাকা=78|চট্টগ্রাম=74" unit="%" source="..."]  (division map, uses App\Support\BdMap)
 *
 * Old shortcodes without "type" keep working (they render as bars). Everything printed is
 * escaped, numbers are validated, and the SVG/HTML is generated here, never taken from input.
 */
final class ArticleVisuals
{
    private const TYPES = ['bar', 'column', 'line', 'pie', 'donut', 'grouped', '3d'];

    private const MAX_POINTS = 12;

    private const MAX_SLICES = 8;

    private const MAX_SERIES = 4;

    private const MAX_STATS = 6;

    private const DEFAULT_SOURCE = 'সূত্র: প্রতিবেদনে উল্লেখিত তথ্য';

    private const STYLES = ['classic', 'gradient', 'outline', 'rounded', 'dark', 'mono'];

    /**
     * Per-style colours. SVG text/fill colours are written as presentation attributes too,
     * because the PNG export does not carry CSS class styles into the cloned SVG.
     */
    private const PALETTES = [
        'classic' => [
            'series' => ['#1f9bd7', '#e04b23', '#2ca02c', '#9467bd'],
            'pie' => ['#1f77b4', '#ff7f0e', '#2ca02c', '#d62728', '#9467bd', '#8c564b', '#e377c2', '#7f7f7f'],
            'primary' => '#d98e04',
            'line' => '#0a5a82',
            'ink' => '#16202a',
            'muted' => '#5b6773',
            'grid' => '#e7eaee',
            'base' => '#b9c0c8',
            'on_color' => '#ffffff',
            'map' => ['#e1e5ea', '#dbe9f4', '#a9cbe3', '#6aa5cf', '#2f78ad', '#0a4f7c'],
            'map_stroke' => '#ffffff',
            'map_light_bins' => [3, 4],
        ],
        'gradient' => [
            'series' => ['#38bdf8', '#fb7185', '#34d399', '#a78bfa'],
            'pie' => ['#2563eb', '#f97316', '#10b981', '#8b5cf6', '#ec4899', '#14b8a6', '#f59e0b', '#6366f1'],
            'primary' => '#2563eb',
            'line' => '#7c3aed',
            'ink' => '#0f172a',
            'muted' => '#475569',
            'grid' => '#e2e8f0',
            'base' => '#94a3b8',
            'on_color' => '#ffffff',
            'map' => ['#eef2ff', '#c7d2fe', '#a5b4fc', '#818cf8', '#4f46e5', '#312e81'],
            'map_stroke' => '#ffffff',
            'map_light_bins' => [3, 4],
        ],
        'outline' => [
            'series' => ['#0f766e', '#b45309', '#4338ca', '#be123c'],
            'pie' => ['#0f766e', '#b45309', '#4338ca', '#be123c', '#0369a1', '#a16207', '#9d174d', '#374151'],
            'primary' => '#0f766e',
            'line' => '#0f766e',
            'ink' => '#0f172a',
            'muted' => '#475569',
            'grid' => '#cbd5e1',
            'base' => '#94a3b8',
            'on_color' => '#16202a',
            'map' => ['#f8fafc', '#e6f4f1', '#c9e7e2', '#9ed4cc', '#5cb3a8', '#0f766e'],
            'map_stroke' => '#64748b',
            'map_light_bins' => [4],
        ],
        'rounded' => [
            'series' => ['#7c9cbf', '#e8a598', '#8fbf9f', '#c9a7d4'],
            'pie' => ['#93b4d6', '#f2b3a5', '#a6cfb3', '#d8b9e0', '#f5d08c', '#9fc9c7', '#e8a9bd', '#b9c6d8'],
            'primary' => '#7c9cbf',
            'line' => '#5b8bb5',
            'ink' => '#1e293b',
            'muted' => '#64748b',
            'grid' => '#e6ebf2',
            'base' => '#aebccb',
            'on_color' => '#1e293b',
            'map' => ['#f4f7fb', '#dbe7f1', '#bfd4e5', '#9bbbd6', '#6f98bd', '#46739c'],
            'map_stroke' => '#ffffff',
            'map_light_bins' => [3, 4],
        ],
        'dark' => [
            'series' => ['#38bdf8', '#fb923c', '#34d399', '#c084fc'],
            'pie' => ['#38bdf8', '#fb923c', '#34d399', '#f472b6', '#a78bfa', '#facc15', '#2dd4bf', '#f87171'],
            'primary' => '#38bdf8',
            'line' => '#22d3ee',
            'ink' => '#e2e8f0',
            'muted' => '#94a3b8',
            'grid' => '#24344d',
            'base' => '#475569',
            'on_color' => '#0b1220',
            'map' => ['#1e293b', '#0e3a5c', '#155e86', '#0e7490', '#06b6d4', '#67e8f9'],
            'map_stroke' => '#0f172a',
            'map_light_bins' => [0, 1, 2],
        ],
        'mono' => [
            'series' => ['#111827', '#4b5563', '#6b7280', '#9ca3af'],
            'pie' => ['#111827', '#374151', '#4b5563', '#6b7280', '#9ca3af', '#d1d5db', '#7c7c7c', '#5c5c5c'],
            'primary' => '#111827',
            'line' => '#111827',
            'ink' => '#111827',
            'muted' => '#4b5563',
            'grid' => '#e5e7eb',
            'base' => '#9ca3af',
            'on_color' => '#ffffff',
            'map' => ['#f4f4f5', '#e4e4e7', '#cbd5e1', '#94a3b8', '#475569', '#111827'],
            'map_stroke' => '#ffffff',
            'map_light_bins' => [3, 4],
        ],
    ];

    private static int $serial = 0;

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

    private static function styleOf(string $raw): string
    {
        $raw = strtolower(trim($raw));

        return in_array($raw, self::STYLES, true) ? $raw : 'classic';
    }

    /** @return array<string, mixed> */
    private static function palette(string $style): array
    {
        return self::PALETTES[$style] ?? self::PALETTES['classic'];
    }

    /** Picks dark or light text for a coloured surface. */
    private static function readableInk(string $hex): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) !== 6) {
            return '#ffffff';
        }

        $channels = [];

        foreach ([0, 2, 4] as $offset) {
            $value = hexdec(substr($hex, $offset, 2)) / 255;
            $channels[] = $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }

        $luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];

        return $luminance > 0.45 ? '#16202a' : '#ffffff';
    }

    /** Mixes a hex colour towards white (positive) or black (negative). */
    private static function shade(string $hex, float $amount): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) !== 6) {
            return $hex;
        }

        $out = '#';

        foreach ([0, 2, 4] as $offset) {
            $channel = hexdec(substr($hex, $offset, 2));
            $channel = $amount >= 0 ? $channel + (255 - $channel) * $amount : $channel * (1 + $amount);
            $out .= str_pad(dechex((int) round(max(0, min(255, $channel)))), 2, '0', STR_PAD_LEFT);
        }

        return $out;
    }

    /** @param array<string, string> $a */
    private static function chart(array $a): string
    {
        $savedId = (int) ($a['id'] ?? 0);

        if ($savedId > 0) {
            $saved = self::savedChart($savedId);

            if ($saved === null) {
                return '<p class="sa-chart-error">সংরক্ষিত গ্রাফটি পাওয়া যায়নি বা প্রকাশ করা হয়নি।</p>';
            }

            $a = $saved;
        }

        $type = in_array($a['type'] ?? 'bar', self::TYPES, true) ? ($a['type'] ?? 'bar') : 'bar';
        $style = self::styleOf($a['style'] ?? '');
        $title = $a['title'] ?? '';
        $unit = mb_substr($a['unit'] ?? '', 0, 12);

        if ($type === 'grouped') {
            $parsed = self::groupedPoints($a['labels'] ?? '', $a['series'] ?? '');

            if ($parsed === null) {
                return '<p class="sa-chart-error">গ্রাফের তথ্য সঠিক নয়।</p>';
            }

            [$labels, $series] = $parsed;

            return self::figure(
                $type,
                $title,
                self::grouped($labels, $series, $unit, $title, $style).self::groupedTable($labels, $series, $unit),
                self::source($a),
                $style
            );
        }

        if ($type === '3d') {
            $items = self::points($a['labels'] ?? '', $a['values'] ?? '', false);

            if ($items === []) {
                return '<p class="sa-chart-error">চার্টের তথ্য সঠিক নয়।</p>';
            }

            return self::figure($type, $title, self::arrow($items, $unit, $title, $style).self::table($items, $unit), self::source($a), $style);
        }

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
            'line' => self::line($items, $unit, $title, $style),
            'pie', 'donut' => self::pie($items, $unit, $title, $type === 'donut', $style),
            default => self::bar($items, $unit, $title),
        };

        return self::figure($type, $title, $plot.self::table($items, $unit), self::source($a), $style);
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

    /** @return array<string, string>|null */
    private static function savedChart(int $id): ?array
    {
        try {
            $chart = \App\Models\Chart::published()->find($id);
        } catch (\Throwable) {
            return null;
        }

        return $chart?->visualAttributes();
    }

    /**
     * A small, non-interactive chart for news cards and thumbnails.
     * Returns SVG markup that only uses spans/SVG, so it is valid inside a link.
     *
     * @return array{svg: string, background: string, label: string}|null
     */
    public static function thumb(string $shortcode): ?array
    {
        if (! preg_match('/^\s*\[chart\b([^\]]*)\]\s*$/u', $shortcode, $match)) {
            return null;
        }

        $a = self::attributes($match[1]);
        $savedId = (int) ($a['id'] ?? 0);

        if ($savedId > 0) {
            $a = self::savedChart($savedId);

            if ($a === null) {
                return null;
            }
        }

        $type = in_array($a['type'] ?? 'bar', self::TYPES, true) ? $a['type'] : 'bar';
        $style = self::styleOf($a['style'] ?? '');
        $palette = self::palette($style);
        $label = trim((string) ($a['title'] ?? ''));
        $unit = mb_substr((string) ($a['unit'] ?? ''), 0, 12);
        $uid = 'sathumb'.(++self::$serial);

        $plot = match (true) {
            $type === 'grouped' => self::thumbGrouped($a, $palette),
            $type === 'line' => self::thumbLine($a, $palette, $style, $uid, $unit),
            $type === 'pie', $type === 'donut' => self::thumbPie($a, $palette, $type === 'donut'),
            default => self::thumbBars($a, $palette, $style, $uid, $type === 'bar', $unit),
        };

        if ($plot === null) {
            return null;
        }

        $background = match ($style) {
            'dark' => '#0f172a',
            'gradient' => '#eef2ff',
            'outline' => '#ffffff',
            'mono' => '#f5f5f5',
            default => '#f7fafc',
        };

        $title = $label !== ''
            ? '<text class="sa-chart-thumb-title" x="18" y="28" font-family="Noto Sans Bengali, Hind Siliguri, system-ui, sans-serif" font-size="13" font-weight="600" fill="'.($style === 'dark' ? $palette['ink'] : $palette['muted']).'">'.e(mb_strimwidth($label, 0, 36, '…')).'</text>'
            : '';

        $grid = '';

        if (! in_array($type, ['pie', 'donut', 'grouped'], true)) {
            $dash = $style === 'outline' ? ' stroke-dasharray="4 4"' : '';

            foreach ([62, 106, 150] as $y) {
                $grid .= '<line x1="18" x2="302" y1="'.$y.'" y2="'.$y.'" stroke="'.$palette['grid'].'" stroke-width="1"'.$dash.'/>';
            }
        }

        return [
            'svg' => '<svg class="sa-chart-thumb-svg" viewBox="0 0 320 200" preserveAspectRatio="xMidYMid meet" aria-hidden="true" focusable="false" font-family="Noto Sans Bengali, Hind Siliguri, system-ui, sans-serif">'.
                '<rect width="320" height="200" fill="'.$background.'"/>'.$title.$grid.$plot.'</svg>',
            'background' => $background,
            'label' => $label,
        ];
    }

    /** @param array<string, string> $a */
    private static function thumbBars(array $a, array $palette, string $style, string $uid, bool $horizontal, string $unit): ?string
    {
        $items = self::points($a['labels'] ?? '', $a['values'] ?? '', false);

        if ($items === []) {
            return null;
        }

        if ($horizontal) {
            $items = array_slice($items, 0, 7);
        }

        $max = max(array_column($items, 'value'));
        $max = $max > 0 ? $max : 1;
        $color = $palette['series'][0];
        $fill = match ($style) {
            'outline' => 'fill="none" stroke="'.$color.'" stroke-width="2.2"',
            'gradient' => 'fill="url(#'.$uid.')"',
            default => 'fill="'.$color.'"',
        };
        $defs = $style === 'gradient'
            ? '<defs><linearGradient id="'.$uid.'" x1="0" y1="1" x2="0" y2="0"><stop offset="0" stop-color="'.$color.'"/><stop offset="1" stop-color="'.self::shade($color, 0.45).'"/></linearGradient></defs>'
            : '';
        $rx = $style === 'rounded' ? '6' : '3';
        $rects = '';
        $labels = '';

        if ($horizontal) {
            $rowH = 128 / count($items);
            $labelSize = count($items) > 5 ? 8.5 : 10;
            $valueSize = count($items) > 5 ? 8.5 : 9.5;
            $chars = count($items) > 5 ? 8 : 10;

            foreach ($items as $i => $item) {
                $width = max(4, ($item['value'] / $max) * 210);
                $y = 46 + $i * $rowH + $rowH * 0.16;
                $height = max(6, $rowH * 0.55);
                $middle = $y + $height / 2;
                $end = 84 + $width;
                $value = self::format($item['value']).$unit;
                $rects .= '<rect x="84" y="'.self::n($y).'" width="'.self::n($width).'" height="'.self::n($height).'" rx="'.$rx.'" '.$fill.'/>';
                $labels .= self::thumbText('sa-thumb-axis', 16, $middle, self::clip($item['label'], $chars), $labelSize, $palette['muted'], 'start');

                if ($end <= 252) {
                    $labels .= self::thumbText('sa-thumb-value', $end + 6, $middle, $value, $valueSize, $palette['ink'], 'start');
                } else {
                    $insideFill = $style === 'outline' ? $palette['ink'] : self::readableInk($color);
                    $labels .= self::thumbText('sa-thumb-value', $end - 6, $middle, $value, $valueSize, $insideFill, 'end');
                }
            }
        } else {
            $slot = 284 / count($items);
            $width = min(30, $slot * 0.52);
            $labelSize = count($items) > 6 ? 8.5 : 10;
            $chars = count($items) > 8 ? 4 : (count($items) > 6 ? 5 : 6);

            foreach ($items as $i => $item) {
                $height = max(4, ($item['value'] / $max) * 118);
                $x = 18 + $i * $slot + ($slot - $width) / 2;
                $y = 172 - $height;
                $rects .= '<rect x="'.self::n($x).'" y="'.self::n($y).'" width="'.self::n($width).'" height="'.self::n($height).'" rx="'.$rx.'" '.$fill.'/>';
                $labels .= self::thumbText('sa-thumb-value', $x + $width / 2, $y - 8, self::format($item['value']).$unit, count($items) > 6 ? 8 : 9.5, $palette['ink']);

                if (count($items) <= 8 || $i % 2 === 0) {
                    $labels .= self::thumbText('sa-thumb-axis', $x + $width / 2, 186, self::clip($item['label'], $chars), $labelSize, $palette['muted']);
                }
            }
        }

        return $defs.$rects.$labels;
    }

    /** @param array<string, string> $a */
    private static function thumbGrouped(array $a, array $palette): ?string
    {
        $parsed = self::groupedPoints($a['labels'] ?? '', $a['series'] ?? '');

        if ($parsed === null) {
            return null;
        }

        [$labels, $series] = $parsed;
        $max = 0.0;

        foreach ($series as $row) {
            $max = max($max, ...$row['values']);
        }

        $max = $max > 0 ? $max : 1;
        $slot = 268 / count($labels);
        $group = min(64, $slot * 0.74);
        $bar = min(13, $group / count($series));
        $rects = '';
        $text = '';
        $legend = '';
        $legendX = 18.0;

        foreach ($series as $j => $row) {
            $name = self::clip($row['name'], 8);
            $color = $palette['series'][$j % 4];
            $legend .= '<circle class="sa-thumb-key" cx="'.self::n($legendX + 3.5).'" cy="42" r="3.5" fill="'.$color.'"/>'.
                self::thumbText('sa-thumb-legend', $legendX + 11, 42, $name, 9, $palette['ink'], 'start');
            $legendX += 15 + mb_strlen($name) * 5.4;
        }

        $ticks = '';

        foreach ([[172, 0.0], [113, $max / 2], [54, $max]] as [$y, $value]) {
            $ticks .= self::thumbText('sa-thumb-axis', 28, $y, self::format(round($value, 2)), 8, $palette['muted'], 'end');
            $ticks .= '<line x1="34" x2="302" y1="'.$y.'" y2="'.$y.'" stroke="'.($y === 172 ? $palette['base'] : $palette['grid']).'" stroke-width="1"/>';
        }

        foreach ($labels as $i => $label) {
            $start = 34 + $i * $slot + ($slot - $group) / 2;

            foreach ($series as $j => $row) {
                $height = max(3, ($row['values'][$i] / $max) * 118);
                $x = $start + $j * $bar;
                $rects .= '<rect x="'.self::n($x).'" y="'.self::n(172 - $height).'" width="'.self::n(max(3, $bar - 2)).'" height="'.self::n($height).'" rx="2" fill="'.$palette['series'][$j % 4].'"/>';
            }

            if (count($labels) <= 8 || $i % 2 === 0) {
                $text .= self::thumbText('sa-thumb-axis', 34 + $i * $slot + $slot / 2, 186, self::clip($label, 5), 9, $palette['muted']);
            }
        }

        return $legend.$ticks.$rects.$text;
    }

    /** @param array<string, string> $a */
    private static function thumbLine(array $a, array $palette, string $style, string $uid, string $unit): ?string
    {
        $items = self::points($a['labels'] ?? '', $a['values'] ?? '', true);

        if (count($items) < 2) {
            return null;
        }

        $values = array_column($items, 'value');
        $min = min(0.0, min($values));
        $max = max($values);

        if ($max <= $min) {
            $max = $min + 1;
        }

        $n = count($items);
        $points = [];
        $text = '';

        foreach ($items as $i => $item) {
            $x = 18 + ($i / ($n - 1)) * 284;
            $y = 172 - (($item['value'] - $min) / ($max - $min)) * 118;
            $points[] = self::n($x).','.self::n($y);

            if ($n <= 6) {
                $text .= self::thumbText('sa-thumb-value', $x, $y - 9, self::format($item['value']).$unit, 9.5, $palette['ink']);
            }
        }

        foreach ($items as $i => $item) {
            $isEdge = $i === 0 || $i === $n - 1;
            $isMiddle = $i === intdiv($n, 2);

            if ($n > 6 && ! $isEdge && ! $isMiddle) {
                continue;
            }

            $x = 18 + ($i / ($n - 1)) * 284;
            $anchor = $i === 0 ? 'start' : ($i === $n - 1 ? 'end' : 'middle');
            $text .= self::thumbText('sa-thumb-axis', $x, 186, self::clip($item['label'], 5), 9, $palette['muted'], $anchor);
        }

        $line = implode(' ', $points);
        $last = explode(',', end($points));
        $defs = '';
        $area = 'fill="'.$palette['line'].'" fill-opacity="0.16"';

        if ($style === 'gradient') {
            $defs = '<defs><linearGradient id="'.$uid.'" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="'.$palette['line'].'" stop-opacity="0.35"/><stop offset="1" stop-color="'.$palette['line'].'" stop-opacity="0.02"/></linearGradient></defs>';
            $area = 'fill="url(#'.$uid.')"';
        }

        return $defs.
            '<polygon points="18,172 '.$line.' 302,172" '.$area.'/>'.
            '<polyline points="'.$line.'" fill="none" stroke="'.$palette['line'].'" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>'.
            '<circle cx="'.$last[0].'" cy="'.$last[1].'" r="4" fill="'.$palette['line'].'" stroke="#ffffff" stroke-width="2"/>'.$text;
    }

    /** @param array<string, string> $a */
    private static function thumbPie(array $a, array $palette, bool $donut): ?string
    {
        $items = array_values(array_filter(
            self::points($a['labels'] ?? '', $a['values'] ?? '', false),
            fn (array $item) => $item['value'] > 0
        ));
        $items = array_slice($items, 0, self::MAX_SLICES);
        $total = array_sum(array_column($items, 'value'));

        if ($items === [] || $total <= 0) {
            return null;
        }

        $radius = $donut ? 52 : 34;
        $width = $donut ? 26 : 68;
        $circumference = 2 * M_PI * $radius;
        $offset = 0.0;
        $circles = '';
        $text = '';
        $legend = '';
        $single = count($items) === 1;

        foreach ($items as $i => $item) {
            $fraction = $item['value'] / $total;
            $color = $palette['pie'][$i % 8];
            $length = $circumference * $fraction;
            $gap = $length > 3 ? 1.5 : 0;
            $circles .= '<circle cx="128" cy="110" r="'.$radius.'" fill="none" stroke="'.$color.'" stroke-width="'.$width.'" stroke-dasharray="'.self::n(max(0.5, $length - $gap)).' '.self::n($circumference).'" stroke-dashoffset="'.self::n(-$offset).'" transform="rotate(-90 128 110)"/>';

            if ($fraction >= 0.05) {
                if ($single) {
                    $lx = 128.0;
                    $ly = 110.0;
                } else {
                    $mid = -M_PI / 2 + (($offset + $length / 2) / $circumference) * 2 * M_PI;
                    $fillRadius = $donut ? $radius : ($radius + $width / 2) * 0.55;
                    $lx = 128 + $fillRadius * cos($mid);
                    $ly = 110 + $fillRadius * sin($mid);
                }

                $text .= self::thumbText('sa-thumb-slice', $lx, $ly, round($fraction * 100).'%', 9.5, self::readableInk($color), 'middle', '600');
            }

            $offset += $length;

            $label = self::clip($item['label'], 9);
            $legend .= '<rect class="sa-thumb-key" x="200" y="'.self::n(48 + $i * 15).'" width="8" height="8" rx="2" fill="'.$color.'"/>'.
                self::thumbText('sa-thumb-legend', 213, 52 + $i * 15, $label, 9, $palette['ink'], 'start');
        }

        return $circles.$text.$legend;
    }

    /** One text node for the compact card chart. */
    private static function thumbText(string $class, float $x, float $y, string $text, float $size, string $fill, string $anchor = 'middle', string $weight = '400'): string
    {
        return '<text class="'.$class.'" x="'.self::n($x).'" y="'.self::n($y).'" font-size="'.self::n($size).'"'.
            ' fill="'.$fill.'" text-anchor="'.$anchor.'" dominant-baseline="middle"'.
            ($weight !== '400' ? ' font-weight="'.$weight.'"' : '').'>'.e($text).'</text>';
    }

    /** Shortens a label to fit the compact chart. */
    private static function clip(string $text, int $chars): string
    {
        $text = trim($text);

        return mb_strlen($text) > $chars ? mb_strimwidth($text, 0, $chars, '…') : $text;
    }

    /**
     * Parses labels="A|B" and series="Fig1=1,2|Fig2=3,4".
     *
     * @return array{0: list<string>, 1: list<array{name: string, values: list<float>}>}|null
     */
    private static function groupedPoints(string $labelsRaw, string $seriesRaw): ?array
    {
        $labels = array_values(array_filter(array_map('trim', explode('|', $labelsRaw)), fn (string $label) => $label !== ''));
        $labels = array_slice($labels, 0, self::MAX_POINTS);

        if (count($labels) < 2) {
            return null;
        }

        $series = [];

        foreach (array_slice(explode('|', $seriesRaw), 0, self::MAX_SERIES) as $line) {
            [$name, $valuesRaw] = array_pad(explode('=', $line, 2), 2, '');
            $name = trim($name);

            if ($name === '' || trim($valuesRaw) === '') {
                continue;
            }

            $values = [];

            foreach (explode(',', $valuesRaw) as $raw) {
                $raw = str_replace([' ', ','], '', strtr(trim($raw), self::DIGITS));

                if (! is_numeric($raw)) {
                    continue 2;
                }

                $values[] = (float) $raw;
            }

            if (count($values) !== count($labels)) {
                continue;
            }

            $series[] = ['name' => $name, 'values' => $values];
        }

        return $series === [] ? null : [$labels, $series];
    }

    /**
     * @param  list<string>  $labels
     * @param  list<array{name: string, values: list<float>}>  $series
     */
    private static function grouped(array $labels, array $series, string $unit, string $title, string $style = 'classic'): string
    {
        $palette = self::palette($style);
        $colors = $palette['series'];
        $values = [];

        foreach ($series as $row) {
            foreach ($row['values'] as $value) {
                $values[] = $value;
            }
        }

        [$top, $step] = self::axis(max($values));
        $n = count($labels);
        $k = count($series);
        $w = max(600, $n * 110);
        $h = 330;
        $left = 64;
        $right = 24;
        $topPad = 18;
        $bottom = 64;
        $plotW = $w - $left - $right;
        $plotH = $h - $topPad - $bottom;
        $baseY = $topPad + $plotH;
        $groupW = $plotW / $n;
        $barW = min(30.0, ($groupW * 0.68) / $k);
        $gap = 4.0;
        $uid = 'sa'.(++self::$serial);
        $defs = '';

        if ($style === 'gradient') {
            $defs = '<defs>';

            foreach ($series as $j => $row) {
                $color = $colors[$j % count($colors)];
                $defs .= '<linearGradient id="'.$uid.'-'.$j.'" x1="0" y1="0" x2="0" y2="1">'.
                    '<stop offset="0" stop-color="'.self::shade($color, 0.42).'"/>'.
                    '<stop offset="1" stop-color="'.$color.'"/>'.
                    '</linearGradient>';
            }

            $defs .= '</defs>';
        }

        $legend = '';

        foreach ($series as $i => $row) {
            $legend .= '<span class="sa-group-key"><i class="sa-swatch" style="background:'.
                $colors[$i % count($colors)].'"></i>'.e($row['name']).'</span>';
        }

        $grid = '';
        $ticks = '';
        $steps = (int) round($top / $step);
        $gridDash = $style === 'outline' ? ' stroke-dasharray="4 4"' : '';

        for ($i = 0; $i <= $steps; $i++) {
            $value = $i * $step;
            $y = $baseY - ($value / $top) * $plotH;

            $grid .= '<line class="sa-group-grid" stroke="'.$palette['grid'].'" stroke-width="1"'.$gridDash.
                ' x1="'.$left.'" y1="'.self::n($y).'" x2="'.($w - $right).'" y2="'.self::n($y).'"/>';
            $ticks .= '<text class="sa-group-tick" font-size="12" fill="'.$palette['muted'].'" x="'.($left - 12).'" y="'.self::n($y).'" text-anchor="end" dominant-baseline="middle">'.
                e(self::format($value)).'</text>';
        }

        $bars = '';

        foreach ($labels as $i => $label) {
            $center = $left + $groupW * ($i + 0.5);
            $start = $center - ($barW * $k + $gap * ($k - 1)) / 2;

            foreach ($series as $j => $row) {
                $value = $row['values'][$i];
                $height = max(0.0, min($plotH, ($value / $top) * $plotH));
                $x = $start + $j * ($barW + $gap);
                $color = $colors[$j % count($colors)];
                $fill = match ($style) {
                    'gradient' => 'fill="url(#'.$uid.'-'.$j.')"',
                    'outline' => 'fill="none" stroke="'.$color.'" stroke-width="2.4"',
                    default => 'fill="'.$color.'"',
                };
                $rx = $style === 'rounded' ? self::n($barW / 2) : '2';

                $bars .= '<rect class="sa-group-bar" x="'.self::n($x).'" y="'.self::n($baseY - $height).
                    '" width="'.self::n($barW).'" height="'.self::n($height).'" rx="'.$rx.'" '.$fill.'>'.
                    '<title>'.e($label.' — '.$row['name'].': '.self::format($value).$unit).'</title></rect>';
            }

            $bars .= '<text class="sa-group-xlabel" font-size="12" fill="'.$palette['ink'].'" x="'.self::n($center).'" y="'.($baseY + 26).'" text-anchor="middle">'.
                e($label).'</text>';
        }

        $axisTitle = $unit !== '' ? $unit : 'মান';

        return '<div class="sa-group-legend" aria-hidden="true">'.$legend.'</div>'.
            '<div class="sa-scroll"><svg class="sa-group-chart" viewBox="0 0 '.$w.' '.$h.'" width="'.$w.'" height="'.$h.'" style="min-width:'.$w.'px" role="img" aria-label="'.e($title).'">'.
            $defs.$grid.
            '<line class="sa-group-base" stroke="'.$palette['base'].'" stroke-width="1.3" x1="'.$left.'" y1="'.self::n($baseY).'" x2="'.($w - $right).'" y2="'.self::n($baseY).'"/>'.
            '<text class="sa-group-axis" font-size="12" fill="'.$palette['muted'].'" transform="translate(18 '.self::n($topPad + $plotH / 2).') rotate(-90)" text-anchor="middle">'.e($axisTitle).'</text>'.
            $ticks.$bars.'</svg></div>';
    }

    /**
     * Chooses a readable y-axis top and tick step (about six ticks).
     *
     * @return array{0: float, 1: float}
     */
    private static function axis(float $max): array
    {
        if ($max <= 0) {
            return [1.0, 0.25];
        }

        $raw = $max / 6;
        $power = 10 ** floor(log10($raw));
        $normal = $raw / $power;

        $step = match (true) {
            $normal <= 1 => 1,
            $normal <= 2 => 2,
            $normal <= 2.5 => 2.5,
            $normal <= 5 => 5,
            default => 10,
        } * $power;

        return [(float) (ceil($max / $step) * $step), (float) $step];
    }

    private static function n(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    /**
     * @param  list<string>  $labels
     * @param  list<array{name: string, values: list<float>}>  $series
     */
    private static function groupedTable(array $labels, array $series, string $unit): string
    {
        $head = '<tr><th>লেবেল</th>';

        foreach ($series as $row) {
            $head .= '<th>'.e($row['name']).'</th>';
        }

        $head .= '</tr>';
        $rows = '';

        foreach ($labels as $i => $label) {
            $rows .= '<tr><td>'.e($label).'</td>';

            foreach ($series as $row) {
                $rows .= '<td>'.e(self::format($row['values'][$i]).$unit).'</td>';
            }

            $rows .= '</tr>';
        }

        return '<details class="sa-visual-data"><summary>তথ্য তালিকা দেখুন</summary>'.
            '<div class="sa-scroll"><table class="sa-group-table"><thead>'.$head.'</thead><tbody>'.$rows.'</tbody></table></div></details>';
    }

    private static function figure(string $type, string $title, string $inner, string $source, string $style = 'classic'): string
    {
        $palette = self::palette($style);
        $vars = '--sa-viz-primary:'.$palette['primary'].';--sa-viz-line:'.$palette['line'].
            ';--sa-viz-1:'.$palette['series'][0].';--sa-viz-2:'.$palette['series'][1].
            ';--sa-viz-3:'.$palette['series'][2].';--sa-viz-4:'.$palette['series'][3].';';

        if ($style === 'gradient') {
            $vars .= '--sa-viz-bar-gradient:linear-gradient(90deg,'.$palette['series'][0].','.$palette['primary'].');'.
                '--sa-viz-col-gradient:linear-gradient(180deg,'.$palette['series'][0].','.$palette['primary'].');';
        }

        return '<figure class="sa-html-chart sa-visual sa-visual-'.$type.' sa-style-'.$style.
            ($title === '' ? ' sa-visual-untitled' : '').'" data-style="'.$style.'" style="'.$vars.'" aria-label="'.e($title).'">'.
            ($title !== '' ? '<figcaption class="sa-html-chart-title">'.e($title).'</figcaption>' : '').
            self::downloadButton().
            $inner.
            '<p class="sa-chart-note">'.e($source).'</p></figure>';
    }

    private static function downloadButton(): string
    {
        return '<button type="button" class="sa-download" aria-label="গ্রাফটি ছবি হিসেবে ডাউনলোড করুন" title="ছবি হিসেবে ডাউনলোড">'.
            '<svg class="sa-download-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'.
            '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M4 20h16"/></svg>'.
            '<span>ছবি ডাউনলোড</span></button>';
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
    private static function line(array $items, string $unit, string $title, string $style = 'classic'): string
    {
        $palette = self::palette($style);
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
        $gridDash = $style === 'outline' ? ' stroke-dasharray="4 4"' : '';

        for ($g = 0; $g < 4; $g++) {
            $line = 8 + $g * 28;
            $grid .= '<line class="sa-line-grid" stroke="'.$palette['grid'].'" stroke-width="1" vector-effect="non-scaling-stroke"'.$gridDash.' x1="0" x2="100" y1="'.$line.'" y2="'.$line.'"/>';
        }

        if ($min < 0) {
            $zero = number_format($y(0.0), 2, '.', '');
            $grid .= '<line class="sa-line-zero" stroke="'.$palette['ink'].'" stroke-width="1.5" vector-effect="non-scaling-stroke" x1="0" x2="100" y1="'.$zero.'" y2="'.$zero.'"/>';
        }

        $defs = '';
        $area = '';

        if ($style === 'gradient') {
            $uid = 'sa'.(++self::$serial);
            $areaPoints = '0,92 '.implode(' ', $points).' 100,92';
            $defs = '<defs><linearGradient id="'.$uid.'" x1="0" y1="0" x2="0" y2="1">'.
                '<stop offset="0" stop-color="'.$palette['line'].'" stop-opacity=".3"/>'.
                '<stop offset="1" stop-color="'.$palette['line'].'" stop-opacity="0"/>'.
                '</linearGradient></defs>';
            $area = '<polygon fill="url(#'.$uid.')" points="'.$areaPoints.'"/>';
        }

        $labels = '';

        foreach ($items as $item) {
            $labels .= '<span>'.e($item['label']).'</span>';
        }

        return '<div class="sa-scroll"><div class="sa-line-chart" role="img" aria-label="'.e($title).'" style="--n:'.$n.'">'.
            '<div class="sa-line-plot"><svg class="sa-line-svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true" focusable="false" style="position:absolute;inset:0;width:100%;height:100%;overflow:visible">'.
            $defs.$grid.$area.'<polyline class="sa-line-path" fill="none" stroke="'.$palette['line'].'" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" points="'.implode(' ', $points).'"/></svg>'.$dots.'</div>'.
            '<div class="sa-line-labels" style="grid-template-columns:repeat('.$n.',minmax(0,1fr))">'.$labels.'</div>'.
            '</div></div>';
    }

    /** @param list<array{label: string, value: float}> $items */
    private static function pie(array $items, string $unit, string $title, bool $donut, string $style = 'classic'): string
    {
        $palette = self::palette($style);
        $colors = $palette['pie'];
        $total = array_sum(array_column($items, 'value'));
        $cx = 100;
        $cy = 100;
        $r = 90;
        $angle = -M_PI / 2;
        $single = count($items) === 1;
        $shapes = '';
        $labels = '';
        $legend = '';
        $defs = '';
        $uid = 'sa'.(++self::$serial);

        foreach ($items as $i => $item) {
            $fraction = $item['value'] / $total;
            $color = $colors[$i % count($colors)];
            $mid = $single ? -M_PI / 2 : $angle + $fraction * M_PI;
            $sliceTitle = $item['label'].': '.self::format($item['value']).$unit;
            $fill = 'fill="'.$color.'"';

            if ($style === 'gradient') {
                $gid = $uid.'-'.$i;
                $defs .= '<linearGradient id="'.$gid.'" x1="0" y1="0" x2="1" y2="1">'.
                    '<stop offset="0" stop-color="'.self::shade($color, 0.42).'"/>'.
                    '<stop offset=".6" stop-color="'.$color.'"/>'.
                    '</linearGradient>';
                $fill = 'fill="url(#'.$gid.')"';
            } elseif ($style === 'outline') {
                $fill = 'fill="'.$color.'" fill-opacity="0.16" stroke="'.$color.'" stroke-width="2"';
            }

            if ($fraction >= 0.9999) {
                $shapes .= '<circle cx="'.$cx.'" cy="'.$cy.'" r="'.$r.'" '.$fill.'><title>'.e($sliceTitle).'</title></circle>';
            } else {
                $end = $angle + $fraction * 2 * M_PI;
                $x1 = $cx + $r * cos($angle);
                $y1 = $cy + $r * sin($angle);
                $x2 = $cx + $r * cos($end);
                $y2 = $cy + $r * sin($end);
                $large = $fraction > 0.5 ? 1 : 0;

                $shapes .= sprintf(
                    '<path d="M%d %d L%.2F %.2F A%d %d 0 %d 1 %.2F %.2F Z" %s><title>%s</title></path>',
                    $cx, $cy, $x1, $y1, $r, $r, $large, $x2, $y2, $fill, e($sliceTitle)
                );

                $angle = $end;
            }

            $legend .= '<li><span class="sa-swatch" style="background:'.$color.'"></span><span class="sa-legend-label">'.
                e($item['label']).'</span></li>';

            $radius = $single ? 0.0 : ($fraction < 0.10 ? 0.8 : ($donut ? 0.76 : 0.62));
            $lx = $cx + $r * $radius * cos($mid);
            $ly = $cy + $r * $radius * sin($mid);
            $lines = [self::format($item['value']).$unit];

            if ($unit !== '%') {
                $lines[] = self::format(round($fraction * 100, 2)).'%';
            }

            $rotation = '';

            if (! $single && $fraction < 0.10) {
                $degrees = fmod(rad2deg($mid) + 360, 360);
                $degrees = $degrees > 90 && $degrees < 270 ? $degrees - 180 : $degrees;
                $rotation = ' transform="rotate('.self::n($degrees).' '.self::n($lx).' '.self::n($ly).')"';
            }

            $labelFill = $style === 'mono' ? self::readableInk($color) : $palette['on_color'];
            $labelHalo = $style === 'outline' ? '#ffffff' : 'rgba(0, 0, 0, .12)';

            $label = '<text class="sa-pie-label" fill="'.$labelFill.'" font-size="9" font-weight="600" paint-order="stroke" stroke="'.$labelHalo.'" stroke-width="0.6" stroke-linejoin="round" x="'.self::n($lx).'" y="'.self::n($ly - (count($lines) > 1 ? 5 : 0)).
                '" text-anchor="middle" dominant-baseline="middle"'.$rotation.'>'.
                '<tspan x="'.self::n($lx).'">'.e($lines[0]).'</tspan>'.

                (count($lines) > 1 ? '<tspan x="'.self::n($lx).'" dy="12">'.e($lines[1]).'</tspan>' : '').
                '</text>';

            $labels .= $label;
        }

        if ($donut) {
            $shapes .= '<circle class="sa-donut-hole" fill="none" cx="'.$cx.'" cy="'.$cy.'" r="52"/>';
        }

        $defs = $defs === '' ? '' : '<defs>'.$defs.'</defs>';

        return '<div class="sa-pie-wrap" role="img" aria-label="'.e($title).'">'.
            '<svg class="sa-pie-svg" viewBox="0 0 200 200" aria-hidden="true" focusable="false">'.$defs.$shapes.$labels.'</svg>'.
            '<ul class="sa-legend">'.$legend.'</ul></div>';
    }

    /**
     * Glossy 3D columns in a red-to-green ramp with a large arrow sweeping upward.
     *
     * @param  list<array{label: string, value: float}>  $items
     */
    private static function arrow(array $items, string $unit, string $title, string $style = 'classic'): string
    {
        $palette = self::palette($style);
        $n = count($items);
        $max = max(array_column($items, 'value'));
        $max = $max > 0 ? $max : 1;

        $w = max(600, $n * 84);
        $h = 360;
        $left = 44;
        $right = 54;
        $topPad = 42;
        $bottom = 74;
        $plotW = $w - $left - $right;
        $plotH = $h - $topPad - $bottom;
        $baseY = $h - $bottom;
        $slot = $plotW / $n;
        $barW = min(58.0, $slot * 0.56);
        $ry = $barW * 0.16;
        $uid = 'sa3d'.(++self::$serial);
        $defs = '<defs>'.
            '<linearGradient id="'.$uid.'-sheen" x1="0" y1="0" x2="1" y2="0">'.
            '<stop offset="0" stop-color="#ffffff" stop-opacity=".38"/>'.
            '<stop offset=".45" stop-color="#ffffff" stop-opacity="0"/>'.
            '</linearGradient>'.
            '<linearGradient id="'.$uid.'-arrow" x1="0" y1="1" x2="1" y2="0">'.
            '<stop offset="0" stop-color="#9aa1a6"/>'.
            '<stop offset=".45" stop-color="#dcdfe2"/>'.
            '<stop offset="1" stop-color="#aeb4b9"/>'.
            '</linearGradient>'.
            '<linearGradient id="'.$uid.'-head" x1="0" y1="1" x2="1" y2="0">'.
            '<stop offset="0" stop-color="#b3b9be"/>'.
            '<stop offset=".55" stop-color="#e6e9eb"/>'.
            '<stop offset="1" stop-color="#bcc2c7"/>'.
            '</linearGradient>';

        $bars = '';
        $labels = '';
        $peakIndex = 0;

        foreach ($items as $i => $item) {
            [$hueFrom, $hueTo] = match ($style) {
                'gradient' => [200, 330],
                'rounded' => [190, 300],
                'dark' => [175, 305],
                'outline' => [190, 285],
                'mono' => [220, 220],
                default => [0, 118],
            };
            $hue = $n === 1
                ? ($style === 'classic' ? 120 : (int) round(($hueFrom + $hueTo) / 2))
                : (int) round($hueFrom + ($i / ($n - 1)) * ($hueTo - $hueFrom));
            $height = max(6.0, min($plotH, ($item['value'] / $max) * $plotH));
            $cx = $left + $slot * ($i + 0.5);
            $x = $cx - $barW / 2;
            $barTop = $baseY - $height;
            $gid = $uid.'-g'.$i;

            if ($item['value'] >= $items[$peakIndex]['value']) {
                $peakIndex = $i;
            }

            if ($style === 'mono') {
                $baseLight = 78 - ($n === 1 ? 0 : ($i / ($n - 1)) * 56);
                $tones = [[6, min(88, $baseLight + 10)], [6, $baseLight], [6, max(14, $baseLight - 14)]];
            } else {
                $tones = match ($style) {
                    'gradient' => [[92, 64], [88, 50], [86, 34]],
                    'rounded' => [[62, 76], [58, 64], [56, 52]],
                    'dark' => [[92, 66], [88, 55], [86, 40]],
                    'outline' => [[85, 60], [82, 46], [80, 32]],
                    default => [[92, 62], [88, 49], [85, 34]],
                };
            }

            $topColor = 'hsl('.$hue.', '.$tones[0][0].'%, '.$tones[0][1].'%)';
            $midColor = 'hsl('.$hue.', '.$tones[1][0].'%, '.$tones[1][1].'%)';
            $lowColor = 'hsl('.$hue.', '.$tones[2][0].'%, '.$tones[2][1].'%)';

            if ($style !== 'outline') {
                $defs .= '<linearGradient id="'.$gid.'" x1="0" y1="0" x2="1" y2="0">'.
                    '<stop offset="0" stop-color="'.$topColor.'"/>'.
                    '<stop offset=".5" stop-color="'.$midColor.'"/>'.
                    '<stop offset="1" stop-color="'.$lowColor.'"/>'.
                    '</linearGradient>';
            }

            $lid = 'hsl('.$hue.', '.$tones[1][0].'%, '.min(90, $tones[1][1] + 14).'%)';

            if ($style === 'outline') {
                $bars .= '<g><title>'.e($item['label'].': '.self::format($item['value']).$unit).'</title>'.
                    '<rect x="'.self::n($x).'" y="'.self::n($barTop).'" width="'.self::n($barW).'" height="'.self::n($baseY - $barTop).'" rx="3" fill="none" stroke="'.$midColor.'" stroke-width="2.4"/>'.
                    '<ellipse cx="'.self::n($cx).'" cy="'.self::n($barTop).'" rx="'.self::n($barW / 2).'" ry="'.self::n($ry).'" fill="none" stroke="'.$topColor.'" stroke-width="2.4"/>'.
                    '<ellipse cx="'.self::n($cx).'" cy="'.self::n($baseY).'" rx="'.self::n($barW / 2).'" ry="'.self::n($ry).'" fill="none" stroke="'.$lowColor.'" stroke-width="1.4" opacity=".55"/>'.
                    '</g>';
            } else {
                $bars .= '<g><title>'.e($item['label'].': '.self::format($item['value']).$unit).'</title>'.
                    '<ellipse cx="'.self::n($cx).'" cy="'.self::n($baseY).'" rx="'.self::n($barW / 2).'" ry="'.self::n($ry).'" fill="'.$lowColor.'"/>'.
                    '<rect x="'.self::n($x).'" y="'.self::n($barTop).'" width="'.self::n($barW).'" height="'.self::n($baseY - $barTop).'" fill="url(#'.$gid.')"/>'.
                    '<rect x="'.self::n($x).'" y="'.self::n($barTop).'" width="'.self::n($barW).'" height="'.self::n($baseY - $barTop).'" fill="url(#'.$uid.'-sheen)"/>'.
                    '<ellipse cx="'.self::n($cx).'" cy="'.self::n($barTop).'" rx="'.self::n($barW / 2).'" ry="'.self::n($ry).'" fill="'.$lid.'"/>'.
                    '<ellipse cx="'.self::n($cx).'" cy="'.self::n($barTop).'" rx="'.self::n($barW / 3.2).'" ry="'.self::n($ry * 0.55).'" fill="#ffffff" opacity=".28"/>'.
                    '</g>';
            }

            $labels .= '<text class="sa-3d-label" font-size="12" fill="'.$palette['muted'].'" x="'.self::n($cx).'" y="'.self::n($baseY + 30).'" text-anchor="middle">'.
                e($item['label']).'</text>';
        }

        $defs .= '</defs>';

        $peakX = $left + $slot * ($peakIndex + 0.5);
        $peakTop = $baseY - max(6.0, min($plotH, ($items[$peakIndex]['value'] / $max) * $plotH));
        $tipX = min($w - 24.0, $peakX + $barW * 0.55 + 28);
        $tipY = max(20.0, $peakTop - 28);

        $startX = $left - 18;
        $startY = $baseY + 4;
        $c1X = $startX + ($tipX - $startX) * 0.28;
        $c1Y = $startY;
        $c2X = $startX + ($tipX - $startX) * 0.74;
        $c2Y = $startY - ($startY - $tipY) * 0.62;

        $headLen = 56.0;
        $headHalf = 40.0;
        $ang = atan2($tipY - $c2Y, $tipX - $c2X);
        $ux = cos($ang);
        $uy = sin($ang);
        $px = -$uy;
        $py = $ux;
        $shaftX = $tipX - $ux * $headLen * 0.8;
        $shaftY = $tipY - $uy * $headLen * 0.8;
        $baseX = $tipX - $ux * $headLen;
        $baseYh = $tipY - $uy * $headLen;
        $notchX = $baseX + $ux * 13;
        $notchY = $baseYh + $uy * 13;

        $shaft = 'M'.self::n($startX).' '.self::n($startY).
            ' C'.self::n($c1X).' '.self::n($c1Y).
            ' '.self::n($c2X).' '.self::n($c2Y).
            ' '.self::n($shaftX).' '.self::n($shaftY);

        $head = '<polygon points="'.
            self::n($tipX).','.self::n($tipY).' '.
            self::n($baseX + $px * $headHalf).','.self::n($baseYh + $py * $headHalf).' '.
            self::n($notchX).','.self::n($notchY).' '.
            self::n($baseX - $px * $headHalf).','.self::n($baseYh - $py * $headHalf).
            '" fill="url(#'.$uid.'-head)" stroke="#8f969b" stroke-width="1.2" stroke-linejoin="round"/>';

        $arrow = '<path d="'.$shaft.'" fill="none" stroke="url(#'.$uid.'-arrow)" stroke-width="30" stroke-linecap="round"/>'.
            '<path d="'.$shaft.'" fill="none" stroke="#ffffff" stroke-opacity=".42" stroke-width="7" stroke-linecap="round" transform="translate(0 -4)"/>'.
            $head;

        return '<div class="sa-scroll"><svg class="sa-3d-chart" viewBox="0 0 '.$w.' '.$h.'" width="'.$w.'" height="'.$h.'" style="min-width:'.$w.'px" role="img" aria-label="'.e($title).'">'.
            $defs.$bars.$arrow.$labels.'</svg></div>';
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

        return self::figure('stats', $a['title'] ?? '', '<div class="sa-stat-grid">'.$cards.'</div>', self::source($a), self::styleOf($a['style'] ?? ''));
    }

    private static function divisionKey(string $name): ?string
    {
        $name = trim(preg_replace('/\s*বিভাগ$/u', '', $name) ?? $name);

        return self::BN_ALIASES[$name] ?? BdMap::keyFor($name, $name);
    }

    /** @param array<string, string> $a */
    private static function map(array $a): string
    {
        $style = self::styleOf($a['style'] ?? '');
        $palette = self::palette($style);
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
        $mapFills = $palette['map'];

        foreach (BdMap::SHAPES as $key => $shape) {
            $has = isset($values[$key]);
            $text = $has ? self::format($values[$key]).$unit : '';
            $x = number_format($shape['x'], 1, '.', '');
            $y = number_format($shape['y'], 1, '.', '');
            $fill = $has ? $mapFills[$bins[$key] + 1] : $mapFills[0];
            $light = $has && in_array($bins[$key], $palette['map_light_bins'], true);
            $labelFill = $has ? ($light ? '#ffffff' : '#16202a') : $palette['ink'];

            $paths .= '<path class="'.($has ? 'sa-map-'.($bins[$key] + 1) : 'sa-map-none').'" fill="'.$fill.
                '" stroke="'.$palette['map_stroke'].'" stroke-width="1.2" d="'.$shape['d'].'">'.
                '<title>'.e(self::LABELS[$key].($has ? ': '.$text : '')).'</title></path>';

            $labels .= '<text class="sa-map-label'.($light ? ' sa-map-label-light' : '').
                '" font-size="12" font-weight="600" fill="'.$labelFill.
                '" x="'.$x.'" y="'.$y.'" text-anchor="middle">'.
                '<tspan x="'.$x.'">'.e(self::LABELS[$key]).'</tspan>'.
                ($has ? '<tspan class="sa-map-num" x="'.$x.'" dy="1.3em">'.e($text).'</tspan>' : '').
                '</text>';
        }

        arsort($values);

        $rank = '';

        foreach ($values as $key => $value) {
            $rank .= '<li><span>'.e(self::LABELS[$key]).'</span><b>'.e(self::format($value).$unit).'</b></li>';
        }

        $scale = '<div class="sa-map-scale"><span>'.e(self::format($min).$unit).'</span>';

        foreach ([1, 2, 3, 4, 5] as $level) {
            $scale .= '<i class="sa-mapbg-'.$level.'" style="background:'.$mapFills[$level].'"></i>';
        }

        $scale .= '<span>'.e(self::format($max).$unit).'</span></div>';

        $warning = $skipped === []
            ? ''
            : '<p class="sa-chart-error">চিহ্নিত করা যায়নি: '.e(implode(', ', array_slice($skipped, 0, 8))).'</p>';

        $inner = '<div class="sa-map-wrap">'.
            '<svg class="sa-map-svg" viewBox="'.BdMap::viewBox().'" role="img" aria-label="'.e($title).
            '" style="flex:0 0 auto;width:min(100%,22rem);height:auto">'.$paths.$labels.'</svg>'.
            '<div class="sa-map-side">'.$scale.'<ol class="sa-map-rank">'.$rank.'</ol></div></div>'.
            $warning.
            '<p class="sa-chart-note">মানচিত্রটি সরলীকৃত; সীমানা আনুমানিক।</p>';

        return self::figure('map', $title, $inner, self::source($a), $style);
    }
}
