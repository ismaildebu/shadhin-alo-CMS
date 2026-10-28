<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\News;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $canReview = $user->isEditorOrAdmin();

        $byStatus = News::visibleTo($user)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // গত ৭ দিনে দিনে কয়টি খবর প্রকাশিত হয়েছে
        $days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->startOfDay());

        $perDay = News::visibleTo($user)
            ->published()
            ->where('published_at', '>=', $days->first())
            ->get(['published_at'])
            ->groupBy(fn ($n) => $n->published_at->format('Y-m-d'))
            ->map->count();

        $chart = $days->map(fn ($d) => [
            'label' => $d->format('d M'),
            'count' => (int) ($perDay[$d->format('Y-m-d')] ?? 0),
        ])->values();

        // পাঠের হিসাব: রিপোর্টার শুধু নিজের খবরের, এডিটর ও অ্যাডমিন সবার
        $reads = fn () => DB::table('news_view_stats')
            ->join('news', 'news.id', '=', 'news_view_stats.news_id')
            ->whereNull('news.deleted_at')
            ->when(! $canReview, fn ($q) => $q->where('news.author_id', $user->id));

        $weekStart = $days->first()->toDateString();

        $viewsToday = (int) $reads()->where('news_view_stats.day', now()->toDateString())->sum('news_view_stats.views');
        $viewsWeek = (int) $reads()->where('news_view_stats.day', '>=', $weekStart)->sum('news_view_stats.views');
        $viewsTotal = (int) News::visibleTo($user)->sum('view_count');

        $perDayViews = $reads()
            ->where('news_view_stats.day', '>=', $weekStart)
            ->selectRaw('news_view_stats.day as d, sum(news_view_stats.views) as total')
            ->groupBy('news_view_stats.day')
            ->pluck('total', 'd');

        $viewChart = $days->map(fn ($d) => [
            'label' => $d->format('d M'),
            'count' => (int) ($perDayViews[$d->format('Y-m-d')] ?? 0),
        ])->values();

        $topRaw = $reads()
            ->where('news_view_stats.day', '>=', $weekStart)
            ->selectRaw('news.id, news.title, news.category_id, sum(news_view_stats.views) as total')
            ->groupBy('news.id', 'news.title', 'news.category_id')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        $categoryNames = Category::whereIn('id', $topRaw->pluck('category_id')->filter())->pluck('name', 'id');

        $topRead = $topRaw->map(fn ($row) => [
            'id' => $row->id,
            'title' => $row->title,
            'category' => $categoryNames[$row->category_id] ?? '—',
            'views' => (int) $row->total,
        ]);

        return view('admin.dashboard', [
            'newsTotal' => (int) $byStatus->sum(),
            'published' => (int) ($byStatus[News::STATUS_PUBLISHED] ?? 0),
            'drafts' => (int) ($byStatus[News::STATUS_DRAFT] ?? 0),
            'pending' => (int) ($byStatus[News::STATUS_PENDING] ?? 0),
            'scheduled' => (int) ($byStatus[News::STATUS_SCHEDULED] ?? 0),
            'archived' => (int) ($byStatus[News::STATUS_ARCHIVED] ?? 0),
            'categoriesCount' => Category::count(),
            'usersCount' => $user->isAdmin() ? User::count() : null,

            'chart' => $chart,
            'chartMax' => max(1, (int) $chart->max('count')),
            'chartTotal' => (int) $chart->sum('count'),

            'viewsToday' => $viewsToday,
            'viewsWeek' => $viewsWeek,
            'viewsTotal' => $viewsTotal,
            'viewChart' => $viewChart,
            'viewChartMax' => max(1, (int) $viewChart->max('count')),
            'topRead' => $topRead,

            'recentNews' => News::visibleTo($user)
                ->with('category')
                ->latest('updated_at')
                ->take(8)
                ->get(),

            'pendingNews' => $canReview
                ? News::visibleTo($user)
                    ->where('status', News::STATUS_PENDING)
                    ->with(['category', 'author'])
                    ->latest('updated_at')
                    ->take(6)
                    ->get()
                : collect(),

            'topCategories' => $canReview
                ? Category::withCount(['news' => fn ($q) => $q->published()])
                    ->orderByDesc('news_count')
                    ->take(5)
                    ->get()
                : collect(),
        ]);
    }
}