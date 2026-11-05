<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TrendCheck;
use App\Models\TrendEvent;
use App\Support\NewsTrends\TrendsImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * "আলোচিত সার্চ" — the desk's view of the trends subsystem.
 *
 * Three tabs, one for each kind of result (drafts that came in, headlines to write,
 * expected trends), plus two tools: run the check now, and probe a URL before its host
 * goes on the allow list. Everything the page shows carries the reason behind it.
 */
class TrendController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage-trends');

        $events = TrendEvent::query()
            ->where('status', '!=', TrendEvent::STATUS_HIDDEN)
            ->with('news:id,title,slug,status')
            ->orderByDesc('id')
            ->limit(400)
            ->get()
            ->groupBy('kind');

        return view('admin.trends.index', [
            'imports' => $events->get(TrendEvent::KIND_IMPORTED, collect()),
            'suggestions' => $events->get(TrendEvent::KIND_SUGGESTION, collect()),
            'forecasts' => $events->get(TrendEvent::KIND_FORECAST, collect()),
            'checks' => TrendCheck::query()->orderByDesc('id')->limit(120)->get(),
            'config' => (array) config('news_trends', []),
            // a Carbon, not max()'s raw string: the page formats this with BanglaTime
            'lastRun' => TrendCheck::query()->latest('id')->first()?->created_at,
        ]);
    }

    /** Run the trending feed now, straight from the page. */
    public function run(): RedirectResponse
    {
        Gate::authorize('manage-trends');

        $config = (array) config('news_trends', []);

        if (empty($config['enabled'])) {
            return redirect()->route('trends.index')
                ->withErrors(['trends' => 'আলোচিত সার্চ থেকে সংগ্রহ বন্ধ আছে (config/news_trends.php → enabled)।']);
        }

        $lock = Cache::lock('news-trends', 600);

        if (! $lock->get()) {
            return redirect()->route('trends.index')
                ->withErrors(['trends' => 'আগের চাল এখনো চলছে; শেষ হলে আবার চেষ্টা করুন।']);
        }

        // পেজ নামানো ও অনুবাদ কয়েক মিনিট নিতে পারে।
        @set_time_limit(600);

        try {
            $result = TrendsImporter::run();
        } finally {
            $lock->release();
        }

        if ($result['error'] !== null) {
            return redirect()->route('trends.index')->withErrors(['trends' => $result['error']]);
        }

        $parts = [];
        $parts[] = $result['imported'].'টি সংবাদ খসড়া হয়েছে';
        $parts[] = $result['suggestions'].'টির জন্য সম্ভাব্য শিরোনাম দেওয়া হয়েছে';

        if ($result['duplicates'] > 0) {
            $parts[] = $result['duplicates'].'টি আগেই ছিল';
        }

        if ($result['rejected'] > 0) {
            $parts[] = $result['rejected'].'টি বাদ পড়েছে';
        }

        if ($result['translation_used'] > 0) {
            $parts[] = $result['translation_used'].' অক্ষর অনুবাদ হয়েছে';
        }

        return redirect()->route('trends.index')->with('success', implode(', ', $parts).'।');
    }

    /**
     * Does this URL behave like a report we may run? Answered without storing anything,
     * so a publisher can be checked before its host is added to the allow list.
     */
    public function verify(Request $request): RedirectResponse
    {
        Gate::authorize('manage-trends');

        $url = trim((string) $request->input('url'));

        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return redirect()->route('trends.index')
                ->withErrors(['trends' => 'একটি সম্পূর্ণ সংবাদ-ঠিকানা দিন (https:// দিয়ে শুরু)।']);
        }

        $result = TrendsImporter::verifySource($url, (array) config('news_trends', []));

        $line = $result['host'].' — '.$result['status']
            .', প্রতিবেদন '.$result['body_chars'].' অক্ষর'
            .($result['image'] ? ', ছবি আছে' : '')
            .($result['published_at'] !== null ? ', তারিখ পাওয়া গেছে' : '')
            .($result['reason'] !== '' ? ' ('.$result['reason'].')' : '')
            .($result['blocked'] ? ' [বাদ-তালিকায়]' : ($result['allowed'] ? ' [অনুমোদিত তালিকায় আছে]' : ' [অনুমোদিত তালিকায় নেই]'));

        return redirect()->route('trends.index')->with('verified', $line);
    }

    /** Mark a row reviewed or take it off the desk's list. */
    public function update(Request $request, TrendEvent $event): RedirectResponse
    {
        Gate::authorize('manage-trends');

        $status = (string) $request->input('status');

        if (! in_array($status, [TrendEvent::STATUS_DONE, TrendEvent::STATUS_HIDDEN, TrendEvent::STATUS_PENDING], true)) {
            return back()->withErrors(['trends' => 'অবস্থা চেনা যায়নি।']);
        }

        $event->status = $status;
        $event->save();

        return redirect()->route('trends.index')->with('success', 'অবস্থা বদল হয়েছে।');
    }

    /**
     * Take a row off the desk's tables for good. The draft it points at is not touched —
     * সংবাদ তালিকাই খসড়ার জায়গা। শব্দটি পরের ঘণ্টায়ও ট্রেন্ডে থাকলে নতুন সারি আসতে পারে,
     * কারণ সেটি তখন নতুন তথ্য।
     */
    public function destroy(TrendEvent $event): RedirectResponse
    {
        Gate::authorize('manage-trends');

        $event->delete();

        return redirect()->route('trends.index')->with('success', 'সারিটি মুছে ফেলা হয়েছে।');
    }

    /**
     * One line of the audit log, removed. Checks are what the dedupe window and the
     * "আগামী ট্রেন্ড" count read, so deleting an "আগেই আছে" row can let that news be
     * offered again — the desk's own lever against a false duplicate, not a cosmetic row.
     */
    public function destroyCheck(TrendCheck $check): RedirectResponse
    {
        Gate::authorize('manage-trends');

        $check->delete();

        return redirect()->route('trends.index')->with('success', 'হিসাবের সারিটি মুছে ফেলা হয়েছে।');
    }
}
