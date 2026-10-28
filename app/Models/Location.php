<?php

namespace App\Models;

use App\Support\FrontendCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Location extends Model
{
    public const DIVISION = 'division';

    public const DISTRICT = 'district';

    public const UPAZILA = 'upazila';

    public const TYPES = [self::DIVISION, self::DISTRICT, self::UPAZILA];

    /** The child level of each type. */
    public const CHILD_TYPE = [
        self::DIVISION => self::DISTRICT,
        self::DISTRICT => self::UPAZILA,
    ];

    protected $fillable = ['parent_id', 'type', 'name_bn', 'name_en', 'slug'];

    protected static function booted(): void
    {
        static::saved(fn () => FrontendCache::flush());
        static::deleted(fn () => FrontendCache::flush());
    }

    public function scopeDivisions(Builder $query): Builder
    {
        return $query->where('type', self::DIVISION);
    }

    public function scopeDistricts(Builder $query): Builder
    {
        return $query->where('type', self::DISTRICT);
    }

    public function scopeUpazilas(Builder $query): Builder
    {
        return $query->where('type', self::UPAZILA);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('name_en');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name_en');
    }

    public function news(): HasMany
    {
        return $this->hasMany(News::class);
    }

    public function isDivision(): bool
    {
        return $this->type === self::DIVISION;
    }

    public function isDistrict(): bool
    {
        return $this->type === self::DISTRICT;
    }

    public function isUpazila(): bool
    {
        return $this->type === self::UPAZILA;
    }

    /**
     * This place plus everything below it: a division includes its districts and their upazilas,
     * a district includes its upazilas. Articles of any of them belong to this place's page.
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        $level = [$this->id];

        // at most two levels below a division
        for ($depth = 0; $depth < 2 && $level; $depth++) {
            $level = self::whereIn('parent_id', $level)->pluck('id')->all();
            $ids = array_merge($ids, $level);
        }

        return $ids;
    }

    /**
     * The place and its ancestors, outermost first: [division, district, upazila].
     *
     * @return Collection<int, Location>
     */
    public function path(): Collection
    {
        $path = collect([$this]);
        $node = $this;

        while ($node->parent_id !== null && $path->count() < 3) {
            $node = $node->parent ?? self::find($node->parent_id);

            if (! $node) {
                break;
            }

            $path->prepend($node);
        }

        return $path->values();
    }

    /** Public URL path segments: [division-slug, district-slug, upazila-slug]. */
    public function urlParameters(): array
    {
        return $this->path()->pluck('slug')->all();
    }

    public function url(): string
    {
        return route('frontend.location.show', $this->urlParameters());
    }

    /** "Savar, Dhaka, Dhaka Division" in Bengali, most specific first. */
    public function fullNameBn(): string
    {
        return $this->path()->reverse()->pluck('name_bn')->implode(', ');
    }
}
