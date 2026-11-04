<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\EpaperPage;
use App\Models\News;
use App\Support\EpaperLayout;
use Illuminate\View\View;

class EpaperController extends Controller
{
    /** আজকের পাতা; আজকে না থাকলে সবচেয়ে শেষে প্রকাশিত পাতা। */
    public function index(): View
    {
        $today = now()->timezone('Asia/Dhaka')->toDateString();

        $page = EpaperPage::published()->whereDate('edition_date', $today)->first()
            ?? EpaperPage::published()->latestFirst()->first();

        abort_if(! $page, 404, 'এখনো কোনো ই-পেপার প্রকাশিত হয়নি।');

        return $this->render($page);
    }

    public function show(string $date): View
    {
        $page = EpaperPage::published()->whereDate('edition_date', $date)->firstOrFail();

        return $this->render($page);
    }

    public function archive(): View
    {
        $pages = EpaperPage::published()->latestFirst()->paginate(20);

        return view('frontend.epaper-archive', compact('pages'));
    }

    /**
     * পাতা দেখানোর আগে খবরগুলো একসাথে নিয়ে আসে; কোনো খবর তখন আর প্রকাশিত নাও থাকতে
     * পারে — তখন শুধু সেই ব্লকটি বাদ পড়ে, পুরো পাতা ভাঙে না।
     */
    private function render(EpaperPage $page): View
    {
        $plan = $page->plan();

        $news = News::published()
            ->with('category')
            ->whereIn('id', EpaperLayout::newsIds($plan))
            ->get()
            ->keyBy('id');

        return view('frontend.epaper', [
            'page' => $page,
            'plan' => EpaperLayout::prune($plan, array_keys($news->all())),
            'news' => $news,
        ]);
    }
}
