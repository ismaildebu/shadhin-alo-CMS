<?php

namespace App\Models;

use App\Support\BengaliText;
use App\Support\FrontendCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    public const MAX_NAME = 50;

    public const MAX_PER_ARTICLE = 10;

    protected $fillable = ['name', 'slug'];

    protected static function booted(): void
    {
        static::saved(fn () => FrontendCache::flush());
        static::deleted(fn () => FrontendCache::flush());
    }

    public function news(): BelongsToMany
    {
        return $this->belongsToMany(News::class, 'article_tag');
    }

    /** Plain, single-line, normalized text: no HTML, no control characters, no doubled spaces. */
    public static function cleanName(?string $raw): string
    {
        $name = strip_tags((string) $raw);
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $name) ?? '';
        $name = preg_replace('/\s+/u', ' ', $name) ?? '';

        return BengaliText::normalize(trim($name));
    }

    /**
     * Split user input such as "রাজনীতি, নির্বাচন ، ঢাকা" into names
     * (ASCII, Arabic and full-width commas, and line breaks).
     *
     * @return list<string>
     */
    public static function splitNames(?string $text): array
    {
        return array_values(array_filter(
            preg_split('/[,\x{060C}\x{FF0C}\r\n]+/u', (string) $text) ?: [],
            fn ($part) => trim($part) !== '',
        ));
    }

    /**
     * The identity of a tag. Case, spelling variants of Bengali letters (য় as one character or two)
     * and punctuation differences do not create a second tag.
     */
    public static function slugFor(string $name): string
    {
        $text = BengaliText::normalize(mb_strtolower(trim($name)));

        // Zero-width joiners are invisible and must not split a word.
        $text = str_replace(["\u{200C}", "\u{200D}"], '', $text);

        $slug = preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '-', $text) ?? '';

        return mb_substr(trim($slug, '-'), 0, 100) ?: 'tag';
    }

    /**
     * Find or create the tags for these names and return their ids (at most MAX_PER_ARTICLE, no duplicates).
     *
     * @param  iterable<string>  $names
     * @return list<int>
     */
    public static function idsForNames(iterable $names): array
    {
        $unique = [];

        foreach ($names as $raw) {
            $name = mb_substr(self::cleanName($raw), 0, self::MAX_NAME);

            if ($name === '') {
                continue;
            }

            $unique[self::slugFor($name)] ??= $name;
        }

        $ids = [];

        foreach (array_slice($unique, 0, self::MAX_PER_ARTICLE, true) as $slug => $name) {
            $tag = self::where('slug', $slug)->first()
                ?? self::createOrFirst(['slug' => $slug], ['name' => $name]);

            $ids[] = $tag->id;
        }

        return $ids;
    }
}
