<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Support\NewsImport\NewsImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class NewsImportController extends Controller
{
    /** Fetch new feed items as drafts, straight from the sidebar button. */
    public function store(): RedirectResponse
    {
        Gate::authorize('create', News::class);

        if (empty(config('news_import.enabled'))) {
            return redirect()->route('news.index')
                ->withErrors(['import' => 'স্বয়ংক্রিয় সংগ্রহ বন্ধ আছে (config/news_import.php → enabled)।']);
        }

        $lock = Cache::lock('news-import', 600);

        if (! $lock->get()) {
            return redirect()->route('news.index')
                ->withErrors(['import' => 'আগের সংগ্রহ এখনো চলছে; শেষ হলে আবার চেষ্টা করুন।']);
        }

        // ছবি নামানো ও পূর্ণ প্রতিবেদন আনার জন্য কয়েক মিনিট লাগতে পারে।
        @set_time_limit(600);

        try {
            $blocks = NewsImporter::run();
        } finally {
            $lock->release();
        }

        $added = 0;
        $duplicates = 0;
        $skipped = 0;
        $problems = [];

        foreach ($blocks as $block) {
            if (! empty($block['error'])) {
                $problems[] = $block['name'].': '.$block['error'];
            }

            foreach ($block['items'] as $item) {
                $state = $item['state'] ?? 'skipped';

                if ($state === 'imported') {
                    $added++;
                } elseif ($state === 'duplicate') {
                    $duplicates++;
                } else {
                    $skipped++;
                }
            }
        }

        if ($added === 0 && $problems !== []) {
            return redirect()->route('news.index')
                ->withErrors(['import' => 'সংবাদ আনা যায়নি — '.implode(' ', $problems)]);
        }

        $parts = [$added > 0
            ? $added.'টি নতুন সংবাদ খসড়া হিসেবে যোগ হয়েছে'
            : 'নতুন কোনো সংবাদ পাওয়া যায়নি'];

        if ($duplicates > 0) {
            $parts[] = $duplicates.'টি আগেই ছিল';
        }

        if ($skipped > 0) {
            $parts[] = $skipped.'টি বাদ পড়েছে';
        }

        if ($problems !== []) {
            $parts[] = count($problems).'টি ফিড আনা যায়নি';
        }

        return redirect()->route('news.index')->with('success', implode(', ', $parts).'।');
    }
}
