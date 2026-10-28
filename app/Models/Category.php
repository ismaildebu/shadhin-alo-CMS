<?php

namespace App\Models;

use App\Models\Concerns\GeneratesUniqueSlug;
use App\Support\FrontendCache;
use App\Support\HomeLayouts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use GeneratesUniqueSlug;

    /** The category whose menu entry opens the division list (the home page sections look it up the same way). */
    public const NATIONWIDE_SLUG = 'saradesh';

        
       /** হোমপেজে ক্যাটাগরির স্টাইল: কী => লেবেল (অ্যাডমিনের "হোমপেজ সাজান" পেজে দেখায়)à¥¤ */
    public const HOME_LAYOUTS = [
        'split'     => 'বামে বড় খবর, ডানে তালিকা',
        'mosaic'    => 'ছবির মোজাইক',
        'scroll'    => 'পাশে সোয়াইপ করা কার্ড',
        'opinion'   => 'উদ্ধৃতি কার্ড (ছবি ছাড়া)',
        'grid4'     => 'চার কার্ড ও বুলেট তালিকা',
        'numbered'  => 'ক্রমিক সংখ্যার তালিকা',
        'dark'      => 'গাঢ় নীল ব্যান্ড',
        'region'    => 'তিন কার্ড ও দুই কলামের তালিকা',
        'trio'      => 'তিনটি লম্বা ছবির কার্ড',
        'wide'      => 'আড়াআড়ি বড় খবর',
        'boxes'     => 'বক্স আইটেম (বর্ডারযুক্ত গ্রিড)',
        'list'      => 'ছবিসহ দুই কলামের তালিকা',
        'slider'    => 'স্লাইডার (ছবি ঘুরবে)',
        'gallery'   => 'ছবির গ্রিড',
        'headlines' => 'শুধু শিরোনামের তালিকা',
        ...HomeLayouts::LABELS,
    ];

    /** স্টাইলের কী => যে ভিউ ফাইল সেটি আঁকে। */
    public const HOME_VIEWS = [
        'split'     => 'frontend.partials.category-block',
        'mosaic'    => 'frontend.partials.blocks.mosaic',
        'scroll'    => 'frontend.partials.blocks.scroll',
        'opinion'   => 'frontend.partials.blocks.opinion',
        'grid4'     => 'frontend.partials.blocks.grid4',
        'numbered'  => 'frontend.partials.blocks.numbered',
        'dark'      => 'frontend.partials.blocks.dark',
        'region'    => 'frontend.partials.blocks.region',
        'trio'      => 'frontend.partials.blocks.trio',
        'wide'      => 'frontend.partials.blocks.wide',
        'boxes'     => 'frontend.partials.blocks.boxes',
        'list'      => 'frontend.partials.blocks.list',
        'slider'    => 'frontend.partials.blocks.slider',
        'gallery'   => 'frontend.partials.blocks.gallery',
        'headlines' => 'frontend.partials.blocks.headlines',
        ...HomeLayouts::VIEWS,
    ];


    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'status',
        'show_in_menu',
        'serial',
        'meta_title',
        'meta_description',
        'show_on_home',
        'home_layout',
        'home_color',
        'home_full',
        'home_serial',
        'menu_serial',
        'menu_more',
        'home_limit',
        'home_bg',
        'home_show_title',
    ];

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'status' => 'boolean',
            'show_in_menu' => 'boolean',
            'serial' => 'integer',
            'show_on_home' => 'boolean',
            'home_full' => 'boolean',
            'home_serial' => 'integer',
            'home_limit' => 'integer',
            'menu_serial' => 'integer',
            'menu_more' => 'boolean',
            'home_show_title' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => FrontendCache::flush());
        static::deleted(fn () => FrontendCache::flush());
        
            static::creating(function (Category $category) {
            if ($category->parent_id) {
                return;
            }

            if (! $category->home_serial) {
                $latest = HomeSection::where('name', 'latest')->value('serial');

                $category->home_serial = $latest
                    ? max(0, (int) $latest - 1)
                    : (int) static::max('home_serial') + 10;
            }

            if (! $category->menu_serial) {
                $category->menu_serial = (int) static::max('menu_serial') + 10;
            }
        });         
        
        
    }

    

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeInMenu(Builder $query): Builder
    {
        return $query->where('show_in_menu', true);
    }

    /** Menu order: by `serial`, and by id when serials tie (all-zero serials are common). */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('serial')->orderBy('id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('serial')->orderBy('id');
    }

    public function news(): HasMany
    {
        return $this->hasMany(News::class);
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Visible to the public: active, and not hanging under an inactive parent.
     */
    public function isPubliclyVisible(): bool
    {
        if (! $this->status) {
            return false;
        }

        return $this->isRoot() || (bool) $this->parent?->status;
    }

    /**
     * This category's id plus the ids of its ACTIVE sub-categories
     * (articles of a sub-category also belong to its parent's page).
     *
     * @return list<int>
     */
    public function categoryIds(): array
    {
        $ids = $this->children()->published()->pluck('id')->all();

        array_unshift($ids, $this->id);

        return $ids;
    }
}

