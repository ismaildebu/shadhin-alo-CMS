<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Models\Video;
use App\Support\FrontendCache;
use App\Support\ArticleDesign;
use App\Support\Settings;
use App\Support\ViewCounter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Display the frontend homepage.
     */
    
        /**
     * Display the frontend homepage.
     */
    public function index(): View
    {
        // Cache homepage blocks for a short period.
        // The existing FrontendCache invalidation mechanism should clear stale data.
        $blocks = Cache::remember(
            FrontendCache::HOME,
            FrontendCache::TTL,
            fn () => $this->buildBlocks()
        );

        

        $perPage = (int) (
            FrontendCache::shared()['homeSections']
                ->get('latest')
                ?->item_limit ?? 12
        );

        $latestNews = News::published()
            ->with('category')
            ->latestPublished()
            ->paginate(max(5, min(30, $perPage)));

        // Categories and breakingNews are supplied by the existing view composer.
        return view('frontend.home', $blocks + compact('latestNews'));
    }

    /**
     * Display a published news article.
     */
       public function show(Request $request, string $slug): View
    {
        $news = News::with(['category', 'tags', 'location.parent.parent'])
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        // গণনায় সমস্যা হলে পেজ যেন না ভাঙে
        try {
            ViewCounter::hit($news, $request);
        } catch (Throwable $e) {
            report($e);
        }

        $design = ArticleDesign::all();

        // Related articles from the same category.
        $relatedNews = News::published()
            ->with('category')
            ->where('category_id', $news->category_id)
            ->where('id', '!=', $news->id)
            ->latestPublished()
            ->take($design['counts']['related'])
            ->get();

        // Latest published articles for the article sidebar.
        $latestNews = News::published()
            ->with('category')
            ->where('id', '!=', $news->id)
            ->latestPublished()
            ->take($design['counts']['latest'])
            ->get();

        // Data for the main-sidebar widgets, loaded only when they are selected.
        $widgetData = [];

        if (in_array('tabs', $design['sidebar'], true)) {
            $widgetData['popularNews'] = News::published()
                ->with('category')
                ->latestPublished()
                ->take(10)
                ->get();
        }

        if (in_array('videos', $design['sidebar'], true)) {
            $widgetData['videos'] = Video::published()
                ->latest('published_at')
                ->latest('id')
                ->take(8)
                ->get();
        }

        return view('frontend.news', compact(
            'news',
            'relatedNews',
            'latestNews',
            'design'
        ) + $widgetData);
    }

        /**
    * Return the next (older) published article of the same category for infinite scroll.
     */
    public function next(string $slug): JsonResponse|Response
    {
        $design = ArticleDesign::all();

        if (! $design['toggles']['autoload']) {
            return response()->noContent();
        }

        $current = News::published()
            ->where('slug', $slug)
            ->firstOrFail(['id', 'category_id', 'published_at']);

        $next = News::with(['category', 'tags', 'location.parent.parent'])
            ->published()
            ->where('category_id', $current->category_id)
            ->where('id', '!=', $current->id)
            ->when(
                $current->published_at,
                fn ($query) => $query->where(
                    fn ($q) => $q
                        ->where('published_at', '<', $current->published_at)
                        ->orWhere(
                            fn ($same) => $same
                                ->where('published_at', $current->published_at)
                                ->where('id', '<', $current->id)
                        )
                ),
                fn ($query) => $query->where('id', '<', $current->id)
            )
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();

        if (! $next) {
            return response()->noContent();
        }

        return response()->json([
            'slug' => $next->slug,
            'html' => view('frontend.partials.article-full', [
                'news' => $next,
                'design' => $design,
                'titleTag' => 'h2',
                'withAds' => false,
            ])->render(),
        ]);
    }


    /**
     * Build the data required by homepage sections.
     */
    private function buildBlocks(): array
    {
        $categories = FrontendCache::shared()['categories'];

        $featuredNews = News::published()
            ->with('category')
            ->where('featured', true)
            ->latestPublished()
            ->take(12)
            ->get();

        $leadNews = News::published()
            ->with('category')
            ->where('is_lead', true)
            ->latestPublished()
            ->first()
            ?? News::published()
                ->with('category')
                ->latestPublished()
                ->first();

        $sideNews = News::published()
            ->with('category')
            ->when(
                $leadNews,
                fn ($query) => $query->where('id', '!=', $leadNews->id)
            )
            ->latestPublished()
            ->take(6)
            ->get();

        // This is a latest-news list, not a true popularity ranking.
        // A real popular-news list requires a reliable view counter.
                $popularNews = $this->popular(15);

        $categoryNews = [];

        foreach ($categories as $category) {
            // Include the category itself and its direct subcategories.
            $categoryIds = $categories
                ->where('parent_id', $category->id)
                ->pluck('id')
                ->prepend($category->id)
                ->all();

            $categoryNews[$category->id] = News::published()
                ->with('category')
                ->whereIn('category_id', $categoryIds)
                ->latestPublished()
                ->take(12)
                ->get();
        }

        $videos = Video::published()
            ->latest('published_at')
            ->latest('id')
            ->take(8)
            ->get();

        return compact(
            'featuredNews',
            'leadNews',
            'sideNews',
            'popularNews',
            'categoryNews',
            'videos'
        );
    }

        /** গত ৭ দিনে সবচেয়ে বেশি পড়া খবর; কম হলে সব সময়ের পাঠ ও সাম্প্রতিক খবর দিয়ে ভরা। */
    private function popular(int $limit)
    {
        $ids = DB::table('news_view_stats')
            ->where('day', '>=', now()->subDays(6)->toDateString())
            ->selectRaw('news_id, sum(views) as total')
            ->groupBy('news_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->pluck('news_id');

        $news = News::published()
            ->with('category')
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (News $n) => $ids->search($n->id))
            ->values();

        if ($news->count() < $limit) {
            $extra = News::published()
                ->with('category')
                ->whereNotIn('id', $news->pluck('id'))
                ->orderByDesc('view_count')
                ->latestPublished()
                ->take($limit - $news->count())
                ->get();

            $news = $news->concat($extra);
        }

        return $news;
    }
}