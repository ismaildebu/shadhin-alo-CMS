<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Location;
use App\Models\News;
use Throwable;

/**
 * Facts for the "no image" placeholder.
 *
 * Only news of the categories in MAP_CATEGORY_SLUGS gets the location maps (an international
 * or entertainment story has no place in Bangladesh to show); every other category gets a plain
 * designed card with the logo and the category name.
 *
 * Lists show many news items, so the location lookup is remembered per location id: each distinct
 * location costs a few queries once, never one set per card. Any problem (missing relation,
 * unknown column) falls back to a neutral placeholder, never an error.
 */
final class NewsFallback
{
    /** Category slugs (or their sub-categories) that show the division and district maps. */
    public const MAP_CATEGORY_SLUGS = ['saradesh'];

    private const NONE = ['key' => null, 'text' => '', 'geo' => null, 'division' => '', 'incident' => ''];

    /** @var array<int, array{key: ?string, text: string, geo: ?array, division: string, incident: string}> */
    private static array $memo = [];

    /** @var array<int, string> */
    private static array $parentSlug = [];

    public static function usesMap(News $news): bool
    {
        $category = $news->category;

        if (! $category) {
            return false;
        }

        if (in_array($category->slug, self::MAP_CATEGORY_SLUGS, true)) {
            return true;
        }

        $parentId = (int) ($category->parent_id ?? 0);

        if ($parentId === 0) {
            return false;
        }

        if (! array_key_exists($parentId, self::$parentSlug)) {
            try {
                self::$parentSlug[$parentId] = (string) Category::query()->whereKey($parentId)->value('slug');
            } catch (Throwable) {
                self::$parentSlug[$parentId] = '';
            }
        }

        return in_array(self::$parentSlug[$parentId], self::MAP_CATEGORY_SLUGS, true);
    }

    /**
     * key       division key of App\Support\BdMap (or null)
     * text      "division › district › upazila" in Bengali
     * geo       district / upazila map location for App\Support\BdGeo (or null)
     * division  Bengali name of the division
     * incident  Bengali name of the upazila, else of the district (the place of the event)
     *
     * @return array{key: ?string, text: string, geo: ?array, division: string, incident: string}
     */
    public static function place(News $news): array
    {
        try {
            $id = (int) ($news->getAttribute($news->location()->getForeignKeyName()) ?? 0);
        } catch (Throwable) {
            return self::NONE;
        }

        if ($id === 0) {
            return self::NONE;
        }

        return self::$memo[$id] ??= self::resolve($news);
    }

    /** @return array{key: ?string, text: string, geo: ?array, division: string, incident: string} */
    private static function resolve(News $news): array
    {
        try {
            $news->loadMissing('location.parent.parent');

            $places = $news->location ? collect($news->location->path()) : collect();
        } catch (Throwable) {
            return self::NONE;
        }

        if ($places->isEmpty()) {
            return self::NONE;
        }

        $division = $places->first();
        $district = $places->first(fn ($p) => $p->type === Location::DISTRICT);
        $upazila = $places->first(fn ($p) => $p->type === Location::UPAZILA);

        return [
            'key' => BdMap::keyFor($division->slug ?? null, $division->name_en ?? null),
            'text' => $places->pluck('name_bn')->filter()->implode(' › '),
            // district and upazila map (null when the place cannot be matched with confidence)
            'geo' => BdGeo::locate($district, $upazila),
            'division' => (string) ($division->name_bn ?? ''),
            'incident' => (string) (($upazila ?? $district)->name_bn ?? ''),
        ];
    }
}
