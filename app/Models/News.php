<?php

namespace App\Models;

use App\Models\Concerns\GeneratesUniqueSlug;
use App\Support\FrontendCache;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class News extends Model
{
    use GeneratesUniqueSlug;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING,
        self::STATUS_SCHEDULED,
        self::STATUS_PUBLISHED,
        self::STATUS_ARCHIVED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'খসড়া',
        self::STATUS_PENDING => 'পর্যালোচনায়',
        self::STATUS_SCHEDULED => 'নির্ধারিত',
        self::STATUS_PUBLISHED => 'প্রকাশিত',
        self::STATUS_ARCHIVED => 'আর্কাইভ',
    ];

    public const TYPE_NEWS = 'news';

    public const TYPE_PHOTO = 'photo';

    public const TYPE_VIDEO = 'video';

    public const TYPE_OPINION = 'opinion';

    public const TYPES = [
        self::TYPE_NEWS => 'সংবাদ',
        self::TYPE_PHOTO => 'ছবি',
        self::TYPE_VIDEO => 'ভিডিও',
        self::TYPE_OPINION => 'মতামত',
    ];

    protected $fillable = [
        'category_id',
        'location_id',
        'author_id',
        'type',
        'title',
        'shoulder',
        'sub_title',
        'summary',
        'seo_title',
        'slug',
        'image',
        'image_caption',
        'image_credit',
        'description',
        'reporter',
        'breaking',
        'featured',
        'is_lead',
        'status',
        'published_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'type' => self::TYPE_NEWS,
    ];

    protected function casts(): array
    {
        return [
            'breaking' => 'boolean',
            'featured' => 'boolean',
            'is_lead' => 'boolean',
            'published_at' => 'datetime',
            'view_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => FrontendCache::flush());
        static::deleted(fn () => FrontendCache::flush());
        static::restored(fn () => FrontendCache::flush());
    }

    /** Visible to the public: published, and its publish time has arrived. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), self::STATUS_PUBLISHED)
            ->where($this->qualifyColumn('published_at'), '<=', now());
    }

    public function scopeLatestPublished(Builder $query): Builder
    {
        return $query->orderByDesc($this->qualifyColumn('published_at'))->orderByDesc($this->qualifyColumn('id'));
    }

    /** Reporters only see their own articles; editors and admins see everything. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isEditorOrAdmin() ? $query : $query->where('author_id', $user->id);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
public function categories(): BelongsToMany
{
    return $this->belongsToMany(
        Category::class,
        'category_news',
        'news_id',
        'category_id'
    );
}

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ArticleRevision::class)->latest('id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /** Date shown to readers. */
    public function getDisplayDateAttribute(): CarbonInterface
    {
        return $this->published_at ?? $this->created_at;
    }


    /** সংরক্ষিত ফটোকার্ডের পূর্ণ ঠিকানা; না থাকলে null। */
    public function getCardUrlAttribute(): ?string
    {
        return $this->card_image ? asset('uploads/cards/'.$this->card_image) : null;
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'article_tag')->orderBy('tags.name');
    }

    /**
     * Replace this article's tags with the given names (created when missing).
     *
     * @param  iterable<string>  $names
     */
    public function syncTagNames(iterable $names): void
    {
        $this->tags()->sync(Tag::idsForNames($names));
        $this->unsetRelation('tags');

        // sync() fires no model events, so flush the front-end cache by hand.
        FrontendCache::flush();
    }
}
