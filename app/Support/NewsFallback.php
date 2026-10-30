<?php

namespace App\Support;

use App\Models\News;
use Throwable;

/**
 * Location facts for the "no image" placeholder (division map key and a text path).
 *
 * Lists show many news items, so the lookup is remembered per location id: each distinct
 * location costs a few queries once, never one set of queries per card. Any problem
 * (missing relation, unknown column) falls back to a neutral placeholder, never an error.
 */
final class NewsFallback
{
    private const NONE = ['key' => null, 'text' => ''];

    /** @var array<int, array{key: ?string, text: string}> */
    private static array $memo = [];

    /** @return array{key: ?string, text: string} */
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

    /** @return array{key: ?string, text: string} */
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

        return [
            'key' => BdMap::keyFor($division->slug ?? null, $division->name_en ?? null),
            'text' => $places->pluck('name_bn')->filter()->implode(' › '),
        ];
    }
}
