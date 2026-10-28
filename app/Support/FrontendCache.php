<?php

namespace App\Support;

use App\Models\Category;
use App\Models\HomeSection;
use App\Models\Location;
use App\Models\MenuItem;
use App\Models\SidebarWidget;
use App\Models\News;
use App\Models\Tag;
use Illuminate\Support\Facades\Cache;

/**
 * Short-lived cache for data every public page needs. It is flushed from the
 * News/Category model events, so editors see changes immediately.
 */
final class FrontendCache
{
    public const TTL = 300; // seconds

    public const SHARED = 'frontend.shared';

    public const HOME = 'frontend.home';

    private static ?array $shared = null;

    /**
     * categories    every active category (top-level and sub), flat: used by the home sections
     * menuCategories active top-level categories shown in the menu, each with its menu children
     * popularTags   most used tags of the last 30 days' published articles ("trending by views" comes with view tracking)
     * divisions     the 8 divisions, for the "সারাদেশ" menu
     * breakingNews  ticker
     */
    public static function shared(): array
    {
        return self::$shared ??= Cache::remember(self::SHARED, self::TTL, fn () => [
            'categories' => Category::published()->ordered()->get(),
            
                'menuCategories' => Category::published()
                ->roots()
                ->inMenu()
                ->orderBy('menu_serial')
                ->ordered()
                ->with(['children' => fn ($q) => $q->published()->inMenu()])
                ->get(),
            'menuItems' => MenuItem::where('visible', true)->orderBy('serial')->get(),

            'sidebarWidgets' => SidebarWidget::where('visible', true)->orderBy('serial')->get(),
            
            // whereHas (not HAVING): portable across MySQL and SQLite.
            'popularTags' => Tag::query()
                ->whereHas('news', fn ($q) => $q->published()->where('news.published_at', '>=', now()->subDays(30)))
                ->withCount(['news as recent_count' => fn ($q) => $q
                    ->published()
                    ->where('news.published_at', '>=', now()->subDays(30))])
                ->orderByDesc('recent_count')
                ->orderBy('name')
                ->limit(20)
                ->get(),
            'homeSections' => HomeSection::orderBy('serial')->get()->keyBy('name'),  
            'divisions' => Location::divisions()->ordered()->get(),
            'breakingNews' => News::published()
                ->where('breaking', true)
                ->latestPublished()
                ->take(20)
                ->get(),
        ]);
    }

    public static function flush(): void
    {
        self::$shared = null;
        Cache::forget(self::SHARED);
        Cache::forget(self::HOME);
    }
}
