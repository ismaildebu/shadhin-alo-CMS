<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Models\Video;
use App\Support\ArticleDesign;
use App\Support\FrontendCache;
use App\Support\ViewCounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class HomeController extends Controller
{
    /**
     * Display the frontend homepage.
     */
    public function index(): View
    {
        // হোমপেজের ব্লকগুলো অল্প সময়ের জন্য ক্যাশ থাকে; ক্যাটাগরি বা খবর বদলালে FrontendCache নিজে মুছে দেয়।
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

        // categories ও breakingNews আসে ভিউ কম্পোজার থেকে।
        return view('frontend.home', $blocks + compact('latestNews'));
    }

    /**
     * Display a published news article.
     */
    public function show(Request $request, string $slug): View
    {
        $news = News::with(['category', 'tags', 'location.parent.parent', 'author:id,name,avatar,bio'])
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

        // একই ক্যাটাগরির সম্পর্কিত খবর
        $relatedNews = News::published()
            ->with('category')
            ->where('category_id', $news->category_id)
            ->where('id', '!=', $news->id)
            ->latestPublished()
            ->take($design['counts']['related'])
            ->get();

        // সাইডবারের সর্বশেষ খবর
        $latestNews = News::published()
            ->with('category')
            ->where('id', '!=', $news->id)
            ->latestPublished()
            ->take($design['counts']['latest'])
            ->get();

        // প্রধান সাইডবারের উইজেটের তথ্য: শুধু যেগুলো বাছা আছে সেগুলোর
        $widgetData = [];

        if (in_array('tabs', $design['sidebar'], true)) {
            $widgetData['popularNews'] = $this->popular(15);
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
     * অটো-লোড হওয়া খবর স্ক্রিনে এলে ব্রাউজার এটি ডাকে; পাঠ গোনা হয়, কিছু ফেরত দেয় না।
     * শুধু জাভাস্ক্রিপ্টের (ajax) অনুরোধ নেয়, তাই সাধারণ লিংক খুলে বা রোবট দিয়ে গোনা যায় না।
     */
    public function view(Request $request, string $slug): Response
    {
        abort_unless($request->ajax(), 404);

        $news = News::published()->where('slug', $slug)->first(['id']);

        if ($news) {
            try {
                ViewCounter::hit($news, $request);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return response()->noContent();
    }



    /**
     * একই ক্যাটাগরির পরের (পুরোনো) প্রকাশিত খবর, অসীম স্ক্রলের জন্য।
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

        $next = News::with(['category', 'tags', 'location.parent.parent', 'author:id,name,avatar,bio'])
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
     * হোমপেজের সেকশনগুলোর তথ্য।
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

        // গত ৭ দিনে সবচেয়ে বেশি পড়া খবর (পাঠ-সংখ্যা থেকে)
        $popularNews = $this->popular(15);

        $categoryNews = [];

        foreach ($categories as $category) {
            // ক্যাটাগরি নিজে ও তার সরাসরি উপ-ক্যাটাগরির খবর
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

    /**
     * গত ৭ দিনে সবচেয়ে বেশি পড়া খবর; কম হলে সব সময়ের পাঠ ও সাম্প্রতিক খবর দিয়ে ভরা।
     * হিসাবটি ২ মিনিট ক্যাশ থাকে, যাতে প্রতিটি পেজ খোলায় ডাটাবেস না খাটে।
     */
    private function popular(int $limit): Collection
    {
        // Cache only the ranking (ids). The articles themselves are read again
        // on every request, so an unpublished or deleted article disappears
        // immediately even while its id is still in the short-lived ranking.
        $ids = Cache::remember('frontend.popular.ids.'.$limit, 120, fn () => DB::table('news_view_stats')
            ->where('day', '>=', now()->subDays(6)->toDateString())
            ->selectRaw('news_id, sum(views) as total')
            ->groupBy('news_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->pluck('news_id')
            ->all());

        $order = array_flip($ids);

        $news = News::published()
            ->with('category')
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (News $n) => $order[$n->id] ?? PHP_INT_MAX)
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