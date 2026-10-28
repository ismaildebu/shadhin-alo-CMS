<?php

namespace App\Support;

/**
 * Dashboard-controlled design of the single news (article) page.
 *
 * Values are stored through the existing Settings store (keys prefixed "article_").
 * Only whitelisted keys, validated hex colors and bounded integers ever reach the
 * page, so the generated <style> block can be printed safely.
 */
final class ArticleDesign
{
    public const LAYOUTS = [
        'classic' => ['label' => 'ক্লাসিক: লেখা ও পাশে সাইডবার', 'main' => 'col-lg-8', 'side' => 'col-lg-4'],
        'wide' => ['label' => 'বড় লেখা, সরু সাইডবার', 'main' => 'col-lg-9', 'side' => 'col-lg-3'],
        'single' => ['label' => 'এক কলাম: সাইডবার নেই', 'main' => 'col-lg-10 mx-auto', 'side' => null],
    ];

    public const SIDES = ['right' => 'ডানে', 'left' => 'বামে'];

    public const FONTS = [
        'serif' => [
            'label' => 'সেরিফ (Noto Serif Bengali)',
            'css' => '"Noto Serif Bengali", "Kalpurush", "SolaimanLipi", serif',
        ],
        'sans' => [
            'label' => 'সানস (Hind Siliguri)',
            'css' => '"Hind Siliguri", "Noto Sans Bengali", system-ui, sans-serif',
        ],
    ];

    public const TITLE_SIZES = [
        'sm' => ['label' => 'ছোট', 'css' => 'clamp(1.6rem, 1.1rem + 2.4vw, 2.5rem)'],
        'md' => ['label' => 'মাঝারি', 'css' => 'clamp(1.85rem, 1.2rem + 3vw, 3.1rem)'],
        'lg' => ['label' => 'বড়', 'css' => 'clamp(2.1rem, 1.3rem + 3.6vw, 3.7rem)'],
    ];

    public const BODY_SIZES = [
        'sm' => ['label' => 'ছোট', 'css' => 'clamp(1.02rem, .96rem + .3vw, 1.18rem)'],
        'md' => ['label' => 'মাঝারি', 'css' => 'clamp(1.1rem, 1.02rem + .35vw, 1.3rem)'],
        'lg' => ['label' => 'বড়', 'css' => 'clamp(1.2rem, 1.1rem + .45vw, 1.45rem)'],
    ];

    public const BODY_WIDTHS = [
        'narrow' => ['label' => 'সরু', 'css' => '38rem'],
        'normal' => ['label' => 'মাঝারি', 'css' => '44rem'],
        'wide' => ['label' => 'চওড়া', 'css' => '52rem'],
    ];

    public const LEADINGS = [
        'compact' => ['label' => 'ঘন', 'css' => '1.8'],
        'normal' => ['label' => 'স্বাভাবিক', 'css' => '2'],
        'relaxed' => ['label' => 'ফাঁকা', 'css' => '2.2'],
    ];

    /** Lead image: every article's image is shown in the same box. */
    public const IMAGE_RATIOS = [
        '16x9' => ['label' => '১৬:৯ (চওড়া)', 'css' => '16 / 9'],
        '3x2' => ['label' => '৩:২', 'css' => '3 / 2'],
        '4x3' => ['label' => '৪:৩', 'css' => '4 / 3'],
        'original' => ['label' => 'আসল অনুপাত (প্রতিটি ছবির সাইজ আলাদা হবে)', 'css' => 'auto'],
    ];

    public const IMAGE_FITS = [
        'cover' => ['label' => 'পুরো জায়গা ভরবে (ছোট ছবি বড় হবে, বড় ছবি ছোট হবে; কিনারা কাটা পড়তে পারে)', 'css' => 'cover'],
        'contain' => ['label' => 'পুরো ছবি দেখাবে (কিছুই কাটা পড়বে না; পাশে ফাঁকা থাকতে পারে)', 'css' => 'contain'],
    ];

    /** Show/hide blocks of the article. */
    public const TOGGLES = [
        'breadcrumb' => 'ব্রেডক্রাম্ব (হোম > ক্যাটাগরি > শিরোনাম)',
        'summary' => 'সারাংশ বক্স',
        'share' => 'শেয়ার বাটন',
        'photocard' => 'ফটোকার্ড ডাউনলোড লিংক (শেয়ার বাটনের সাথে)',
        'location' => 'স্থান (বিভাগ, জেলা, উপজেলা)',
        'tags' => 'ট্যাগ',
        'related_bottom' => 'লেখার নিচে সম্পর্কিত সংবাদ',
        'autoload' => 'লেখার শেষে একই ক্যাটাগরির পরের খবর অটো লোড',
        'no_image_card' => 'ছবি না থাকলে লোগো ও অবস্থানের মানচিত্রের ডিজাইন দেখাবে',
    ];

    /** Sidebar parts of the article page; "slot" is the existing ad slot name. */
    public const WIDGETS = [
        'ad_top' => ['label' => 'বিজ্ঞাপন: সাইডবারের ওপরে', 'slot' => 'sidebar_top'],
        'latest' => ['label' => 'সর্বশেষ সংবাদ', 'slot' => null],
        'ad_middle' => ['label' => 'বিজ্ঞাপন: সাইডবারের মাঝখানে', 'slot' => 'sidebar_middle'],
        'related' => ['label' => 'সম্পর্কিত সংবাদ', 'slot' => null],
        'tags' => ['label' => 'জনপ্রিয় ট্যাগ', 'slot' => null],
        'ad_bottom' => ['label' => 'বিজ্ঞাপন: সাইডবারের নিচে', 'slot' => 'sidebar_bottom'],

        // Same widget files as the main sidebar (frontend/partials/widgets/*).
        'tabs' => ['label' => 'সর্বশেষ ও জনপ্রিয় (ট্যাব)', 'slot' => null, 'note' => 'মূল সাইডবারের ট্যাব; খবরের সংখ্যা "সাইডবার সাজান" পেজ থেকে ঠিক হয়'],
        'videos' => ['label' => 'সাম্প্রতিক ভিডিও', 'slot' => null, 'note' => 'মূল সাইডবারের ভিডিও; সংখ্যা "সাইডবার সাজান" পেজ থেকে ঠিক হয়'],
        'facebook' => ['label' => 'ফেসবুক পেজ', 'slot' => null, 'note' => 'সাইট সেটিংয়ে ফেসবুক লিংক না থাকলে কিছু দেখাবে না'],
        'poll' => ['label' => 'অনলাইন পোল', 'slot' => null, 'note' => 'মূল সাইডবারের পোল'],
    ];

    public const COUNTS = [
        'latest' => ['label' => 'সর্বশেষ সংবাদ', 'min' => 3, 'max' => 10, 'default' => 8],
        'related' => ['label' => 'সম্পর্কিত সংবাদ', 'min' => 2, 'max' => 8, 'default' => 6],
        'tags' => ['label' => 'জনপ্রিয় ট্যাগ', 'min' => 4, 'max' => 20, 'default' => 20],
        'autoload' => ['label' => 'অটো লোড হওয়া খবর', 'min' => 1, 'max' => 10, 'default' => 5],
    ];

    /** Spread into Settings::DEFAULTS. The defaults reproduce the current article page. */
    public const DEFAULTS = [
        'article_layout' => 'classic',
        'article_sidebar_side' => 'right',
        'article_accent' => '#d98e04',
        'article_link' => '#0a5a82',
        'article_font' => 'serif',
        'article_title_size' => 'md',
        'article_body_size' => 'md',
        'article_body_width' => 'normal',
        'article_leading' => 'normal',
        'article_image_ratio' => '16x9',
        'article_image_fit' => 'cover',

        'article_show_breadcrumb' => '1',
        'article_show_summary' => '1',
        'article_show_share' => '1',
        'article_show_photocard' => '1',
        'article_show_location' => '1',
        'article_show_tags' => '1',
        'article_show_related_bottom' => '1',
        'article_show_autoload' => '1',
        'article_show_no_image_card' => '1',

        'article_sidebar_order' => 'ad_top,latest,ad_middle,related,tags,ad_bottom',
        'article_sidebar_hidden' => '',

        'article_latest_count' => '8',
        'article_related_count' => '6',
        'article_tags_count' => '20',
        'article_autoload_count' => '5',
    ];

    /**
     * Normalized, safe configuration for the article page.
     *
     * @return array{
     *     opt: array<string, string>,
     *     main_col: string,
     *     side_col: ?string,
     *     toggles: array<string, bool>,
     *     sidebar: list<string>,
     *     counts: array<string, int>,
     *     css: string
     * }
     */
    public static function all(): array
    {
        $s = Settings::all();

        $opt = [
            'layout' => self::pick($s['article_layout'] ?? '', self::LAYOUTS, 'classic'),
            'side' => self::pick($s['article_sidebar_side'] ?? '', self::SIDES, 'right'),
            'font' => self::pick($s['article_font'] ?? '', self::FONTS, 'serif'),
            'title_size' => self::pick($s['article_title_size'] ?? '', self::TITLE_SIZES, 'md'),
            'body_size' => self::pick($s['article_body_size'] ?? '', self::BODY_SIZES, 'md'),
            'body_width' => self::pick($s['article_body_width'] ?? '', self::BODY_WIDTHS, 'normal'),
            'leading' => self::pick($s['article_leading'] ?? '', self::LEADINGS, 'normal'),
            'image_ratio' => self::pick($s['article_image_ratio'] ?? '', self::IMAGE_RATIOS, '16x9'),
            'image_fit' => self::pick($s['article_image_fit'] ?? '', self::IMAGE_FITS, 'cover'),
            'accent' => Colors::isHex($s['article_accent'] ?? null) ? $s['article_accent'] : self::DEFAULTS['article_accent'],
            'link' => Colors::isHex($s['article_link'] ?? null) ? $s['article_link'] : self::DEFAULTS['article_link'],
        ];

        $toggles = [];

        foreach (array_keys(self::TOGGLES) as $key) {
            $toggles[$key] = ($s['article_show_'.$key] ?? '1') === '1';
        }

        $hidden = self::csv($s['article_sidebar_hidden'] ?? '');
        // Only widgets present in the saved order are shown, so widgets added later stay hidden
        // until an editor saves the form.
        $sidebar = array_values(array_diff(self::csv($s['article_sidebar_order'] ?? ''), $hidden));

        // No sidebar parts selected: use the single-column grid so the page has no empty gap.
        $columns = self::LAYOUTS[$sidebar === [] ? 'single' : $opt['layout']];

        $counts = [];

        foreach (self::COUNTS as $key => $rule) {
            $counts[$key] = self::clamp($s['article_'.$key.'_count'] ?? '', $rule);
        }

        return [
            'opt' => $opt,
            'main_col' => $columns['main'],
            'side_col' => $columns['side'],
            'toggles' => $toggles,
            'sidebar' => $sidebar,
            'counts' => $counts,
            'css' => self::css($opt),
        ];
    }

    /**
     * Sidebar parts in the saved order, hidden ones included (for the admin form).
     *
     * @return list<array{key: string, label: string, note: ?string, visible: bool}>
     */
    public static function sidebarRows(): array
    {
        $s = Settings::all();
        $saved = self::csv($s['article_sidebar_order'] ?? '');
        $hidden = self::csv($s['article_sidebar_hidden'] ?? '');

        return array_map(
            fn (string $key) => [
                'key' => $key,
                'label' => self::WIDGETS[$key]['label'],
                'note' => self::WIDGETS[$key]['note'] ?? null,
                'visible' => in_array($key, $saved, true) && ! in_array($key, $hidden, true),
            ],
            self::orderedKeys($s['article_sidebar_order'] ?? '')
        );
    }

    /**
     * Turns the submitted sidebar rows into the two stored settings.
     *
     * @param  array<string, array<string, mixed>>  $rows  widgets[key][serial|show]
     * @return array{article_sidebar_order: string, article_sidebar_hidden: string}
     */
    public static function sidebarValues(array $rows): array
    {
        $sort = [];
        $position = 0;

        foreach (array_keys(self::WIDGETS) as $key) {
            $position++;
            $serial = (int) ($rows[$key]['serial'] ?? $position * 10);
            $sort[$key] = [$serial, $position];
        }

        uasort($sort, fn (array $a, array $b) => $a <=> $b);

        $order = array_keys($sort);
        $hidden = array_values(array_filter(
            $order,
            fn (string $key) => ! isset($rows[$key]['show'])
        ));

        return [
            'article_sidebar_order' => implode(',', $order),
            'article_sidebar_hidden' => implode(',', $hidden),
        ];
    }

    /** @param array<string, string> $opt */
    private static function css(array $opt): string
    {
        $vars = [
            '--sa-amber' => $opt['accent'],
            '--sa-link' => $opt['link'],
            '--sa-link-hover' => 'color-mix(in srgb, '.$opt['link'].' 75%, #000)',
            '--sa-font-display' => self::FONTS[$opt['font']]['css'],
            '--sa-title-size' => self::TITLE_SIZES[$opt['title_size']]['css'],
            '--sa-body-size' => self::BODY_SIZES[$opt['body_size']]['css'],
            '--sa-body-width' => self::BODY_WIDTHS[$opt['body_width']]['css'],
            '--sa-body-leading' => self::LEADINGS[$opt['leading']]['css'],
            '--sa-image-ratio' => self::IMAGE_RATIOS[$opt['image_ratio']]['css'],
            '--sa-image-fit' => self::IMAGE_FITS[$opt['image_fit']]['css'],
        ];

        $css = '';

        foreach ($vars as $name => $value) {
            $css .= $name.':'.$value.';';
        }

        return ':root{'.$css.'}';
    }

    /** @param array<string, mixed> $allowed */
    private static function pick(mixed $value, array $allowed, string $fallback): string
    {
        return is_string($value) && array_key_exists($value, $allowed) ? $value : $fallback;
    }

    /** @param array{min: int, max: int, default: int} $rule */
    private static function clamp(mixed $value, array $rule): int
    {
        $number = is_numeric($value) ? (int) $value : $rule['default'];

        return max($rule['min'], min($rule['max'], $number));
    }

    /** @return list<string> known widget keys, de-duplicated, in the given order */
    private static function csv(string $value): array
    {
        $keys = array_map('trim', explode(',', $value));

        return array_values(array_unique(array_filter(
            $keys,
            fn (string $key) => isset(self::WIDGETS[$key])
        )));
    }

    /** @return list<string> saved order first, then any widget missing from it */
    private static function orderedKeys(string $saved): array
    {
        $keys = self::csv($saved);

        return array_merge($keys, array_values(array_diff(array_keys(self::WIDGETS), $keys)));
    }
}
