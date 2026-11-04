<?php

namespace App\Support;

use App\Models\News;
use App\Models\SidebarWidget;
use App\Models\Video;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * সাইডবারের উইজেটগুলোর তথ্য।
 *
 * হোম ও সংবাদ পেজ এটি নিজে থেকেই পাঠায়; বিভাগ, ট্যাগ, স্থান ও ভিডিও পেজে
 * পাঠাতে হয় না — for() শুধু যেগুলো নেই সেগুলোই জোগাড় করে।
 */
final class SidebarData
{
    /** প্রতিটি উইজেট কোন কোন ভেরিয়েবল চায়। */
    private const NEEDS = [
        'tabs' => ['latestNews' => 15, 'popularNews' => 15],
        'videos' => ['videos' => 8],
    ];

    /**
     * @param  Collection<int, SidebarWidget>  $widgets
     * @param  array<string, mixed>  $have  পেজটি ইতিমধ্যে যা পাঠিয়েছে
     * @return array<string, Collection>
     */
    public static function for(Collection $widgets, array $have = []): array
    {
        $names = $widgets->pluck('name')->all();
        $data = [];

        foreach (self::NEEDS as $widget => $needs) {
            if (! in_array($widget, $names, true)) {
                continue;
            }

            foreach ($needs as $key => $limit) {
                // LengthAwarePaginator (ভিডিও তালিকা) উইজেটের take() চালে না, তাই Collection লাগবে।
                if (($have[$key] ?? null) instanceof Collection) {
                    continue;
                }

                $data[$key] = match ($key) {
                    'latestNews' => self::latest($limit),
                    'popularNews' => self::popular($limit),
                    'videos' => self::videos($limit),
                };
            }
        }

        return $data;
    }

    public static function latest(int $limit): Collection
    {
        return News::published()
            ->with('category')
            ->latestPublished()
            ->take($limit)
            ->get();
    }

    public static function videos(int $limit): Collection
    {
        return Video::published()
            ->latest('published_at')
            ->latest('id')
            ->take($limit)
            ->get();
    }

    /**
     * গত ৭ দিনে সবচেয়ে বেশি পড়া খবর; কম হলে সব সময়ের পাঠ ও সাম্প্রতিক খবর দিয়ে ভরা।
     * হিসাবটি ২ মিনিট ক্যাশ থাকে, যাতে প্রতিটি পেজ খোলায় ডাটাবেস না খাটে।
     */
    public static function popular(int $limit): Collection
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
