<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

trait GeneratesUniqueSlug
{
    public static function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        // Str::slug() transliterates Bengali to Latin; fall back if nothing is left.
        $base = Str::limit(Str::slug($source), 240, '') ?: 'item';
        $slug = $base;
        $count = 1;

        while (
            static::slugQuery()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$count++;
        }

        return $slug;
    }

    private static function slugQuery()
    {
        // Soft-deleted rows still occupy their slug (unique index).
        // (withTrashed() is a query-builder macro, so method_exists() cannot detect it.)
        return in_array(SoftDeletes::class, class_uses_recursive(static::class), true)
            ? static::withTrashed()
            : static::query();
    }
}
