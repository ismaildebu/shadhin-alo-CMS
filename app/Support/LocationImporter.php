<?php

namespace App\Support;

use App\Models\Location;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Loads the division / district / upazila tree from a JSON file.
 * Safe to run again: places are matched by (parent, slug), names are updated, nothing is deleted.
 */
final class LocationImporter
{
    public const DEFAULT_FILE = 'database/data/bd-locations.json';

    /** @return array{created: int, updated: int} */
    public static function import(?string $file = null): array
    {
        $path = $file ?? base_path(self::DEFAULT_FILE);

        if (! is_file($path)) {
            throw new InvalidArgumentException("Location data file not found: {$path}");
        }

        $tree = json_decode((string) file_get_contents($path), true);

        if (! is_array($tree)) {
            throw new InvalidArgumentException("Location data file is not valid JSON: {$path}");
        }

        $stats = ['created' => 0, 'updated' => 0];

        foreach ($tree as $division) {
            $divisionRow = self::upsert(null, Location::DIVISION, $division, $stats);

            foreach ($division['districts'] ?? [] as $district) {
                $districtRow = self::upsert($divisionRow->id, Location::DISTRICT, $district, $stats);

                foreach ($district['upazilas'] ?? [] as $upazila) {
                    self::upsert($districtRow->id, Location::UPAZILA, $upazila, $stats);
                }
            }
        }

        FrontendCache::flush();

        return $stats;
    }

    public static function slugFor(string $nameEn): string
    {
        // "Cox's Bazar" -> "coxs-bazar" (apostrophes dropped, not turned into separators)
        return Str::slug(str_replace("'", '', $nameEn)) ?: 'place';
    }

    private static function upsert(?int $parentId, string $type, array $row, array &$stats): Location
    {
        $slug = self::slugFor($row['name_en']);

        $location = Location::firstOrNew(['parent_id' => $parentId, 'slug' => $slug]);
        $isNew = ! $location->exists;

        $location->fill([
            'type' => $type,
            'name_bn' => $row['name_bn'],
            'name_en' => $row['name_en'],
        ])->save();

        $stats[$isNew ? 'created' : 'updated']++;

        return $location;
    }
}
