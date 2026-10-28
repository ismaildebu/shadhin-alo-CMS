<?php

namespace App\Support;

/**
 * Behaviour metadata for the newer home-page category block styles.
 *
 * LABELS and VIEWS are spread into Category::HOME_LAYOUTS / Category::HOME_VIEWS,
 * so the dropdown and the renderer pick the new styles up automatically.
 * META adds what those constants cannot express: how a style is placed on the
 * page and how many items it needs.
 *
 * width   'column' = consecutive blocks are grouped side by side in one row
 *                    (per_row columns when full width, per_row_narrow when the
 *                    block sits beside the sidebar); 'block' = takes a full row alone.
 * header  heading variant the partial should draw.
 * limit   min == max means the structure needs an exact item count.
 *
 * Styles not listed here (all the older ones) keep their current behaviour.
 */
final class HomeLayouts
{
    /** Dropdown labels, spread into Category::HOME_LAYOUTS. */
    public const LABELS = [
        'thumb_list' => 'ছোট ছবি বামে, শিরোনাম ডানে (তালিকা)',
        'lead_sides' => 'মাঝে বড় লিড, দুই পাশে দুটি করে কার্ড',
        'mag_mix' => 'ম্যাগাজিন মিশ্র (লিড, পাশে কার্ড, নিচে তিন আইটেম)',
        'img_heads' => 'একটি ছবিসহ খবর, নিচে শুধু শিরোনাম',
        'lead_boxes' => 'বামে বড় লিড, ডানে বক্স কার্ড',
        'lead_rlist' => 'বামে বড় লিড, ডানে তালিকা (ছবি ডানে, সময়সহ)',
        'desh_map' => 'দেশজুড়ে: লিড, কার্ড, বিভাগের মানচিত্র ও ফিল্টার',
    ];

    /** New styles offered to the home page's lead ("hero") section: the ones built around a lead story. */
    public const HERO_LABELS = [
        'lead_sides' => self::LABELS['lead_sides'],
        'mag_mix' => self::LABELS['mag_mix'],
        'lead_boxes' => self::LABELS['lead_boxes'],
        'lead_rlist' => self::LABELS['lead_rlist'],
    ];

    /** New styles offered to the "featured" section (desh_map needs a category, so it is not offered). */
    public const FEATURED_LABELS = [
        'thumb_list' => self::LABELS['thumb_list'],
        'lead_sides' => self::LABELS['lead_sides'],
        'mag_mix' => self::LABELS['mag_mix'],
        'img_heads' => self::LABELS['img_heads'],
        'lead_boxes' => self::LABELS['lead_boxes'],
        'lead_rlist' => self::LABELS['lead_rlist'],
    ];

    /** View files, spread into Category::HOME_VIEWS. */
    public const VIEWS = [
        'thumb_list' => 'frontend.partials.blocks.thumb-list',
        'lead_sides' => 'frontend.partials.blocks.lead-sides',
        'mag_mix' => 'frontend.partials.blocks.mag-mix',
        'img_heads' => 'frontend.partials.blocks.img-heads',
        'lead_boxes' => 'frontend.partials.blocks.lead-boxes',
        'lead_rlist' => 'frontend.partials.blocks.lead-rlist',
        'desh_map' => 'frontend.partials.blocks.desh-map',
    ];

    public const META = [
        'thumb_list' => [
            'width' => 'column',
            'per_row' => 3,
            'per_row_narrow' => 2,
            'header' => 'chevron',
            'limit' => ['min' => 3, 'max' => 6, 'default' => 4],
        ],
        'lead_sides' => [
            'width' => 'block',
            'per_row' => 1,
            'header' => 'chevron',
            'limit' => ['min' => 5, 'max' => 5, 'default' => 5],
        ],
        'mag_mix' => [
            'width' => 'block',
            'per_row' => 1,
            'header' => 'plain',
            'limit' => ['min' => 9, 'max' => 9, 'default' => 9],
        ],
        'img_heads' => [
            'width' => 'column',
            'per_row' => 4,
            'per_row_narrow' => 2,
            'header' => 'square_rule',
            'limit' => ['min' => 3, 'max' => 6, 'default' => 4],
        ],
        'lead_boxes' => [
            'width' => 'block',
            'per_row' => 1,
            'header' => 'chevron',
            'limit' => ['min' => 3, 'max' => 5, 'default' => 3],
        ],
        'lead_rlist' => [
            'width' => 'block',
            'per_row' => 1,
            'header' => 'chevron',
            'limit' => ['min' => 3, 'max' => 7, 'default' => 5],
        ],
        'desh_map' => [
            'width' => 'block',
            'per_row' => 1,
            'header' => 'chevron',
            'limit' => ['min' => 5, 'max' => 5, 'default' => 5],
        ],
    ];

    /** @return array<string, mixed>|null */
    public static function get(?string $key): ?array
    {
        return $key === null ? null : (self::META[$key] ?? null);
    }

    public static function isColumn(?string $key): bool
    {
        return (self::get($key)['width'] ?? null) === 'column';
    }

    /** Columns per row; $narrow = the block sits beside the sidebar (less room). */
    public static function perRow(?string $key, bool $narrow = false): int
    {
        $meta = self::get($key);

        if ($narrow) {
            return (int) ($meta['per_row_narrow'] ?? $meta['per_row'] ?? 1);
        }

        return (int) ($meta['per_row'] ?? 1);
    }

    public static function header(?string $key): string
    {
        return self::get($key)['header'] ?? 'plain';
    }

    /**
     * Item-count limits for a layout; falls back to the caller's limits
     * (the existing CATEGORY_LIMIT) for styles that are not listed here.
     *
     * @param  array{label: string, min: int, max: int}  $fallback
     * @return array{label: string, min: int, max: int, default?: int}
     */
    public static function limitFor(?string $key, array $fallback): array
    {
        $limit = self::get($key)['limit'] ?? null;

        return $limit ? ['label' => $fallback['label']] + $limit : $fallback;
    }

    /** Default item count for a layout, or null when the style's own default applies. */
    public static function defaultLimit(?string $key): ?int
    {
        return self::get($key)['limit']['default'] ?? null;
    }
}
