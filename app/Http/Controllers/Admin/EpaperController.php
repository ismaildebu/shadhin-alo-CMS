<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EpaperPage;
use App\Models\Media;
use App\Models\News;
use App\Support\EpaperLayout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use JsonException;

class EpaperController extends Controller
{
    public function index(): View
    {
        return view('admin.epaper.index', [
            'pages' => EpaperPage::latestFirst()->paginate(20),
            'today' => $this->today(),
        ]);
    }

    /** তারিখ দিয়ে নতুন সংস্করণ (আগে থেকে থাকলে সেটিতেই যায়)। */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(
            ['edition_date' => ['required', 'date_format:Y-m-d']],
            ['edition_date.required' => 'কোন তারিখের পাতা বানাতে চান তা লিখুন।',
             'edition_date.date_format' => 'তারিখ ২০২৬-১১-০৪ এই রূপে (Y-m-d) লিখুন।'],
        );

        $existing = EpaperPage::whereDate('edition_date', $data['edition_date'])->first();

        if ($existing) {
            return redirect()->route('epaper.edit', $existing);
        }

        $page = EpaperPage::create([
            'edition_date' => $data['edition_date'],
            'layout' => EpaperLayout::blank(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('epaper.edit', $page)
            ->with('success', $page->edition_date->format('d F Y').' তারিখের পাতা তৈরি হয়েছে।');
    }

    public function edit(EpaperPage $epaper): View
    {
        $plan = $epaper->plan();

        return view('admin.epaper.builder', [
            'page' => $epaper,
            'plan' => $plan,
            'news' => $this->newsByIds(EpaperLayout::newsIds($plan)),
            'limits' => [
                'minWidth' => EpaperLayout::MIN_WIDTH,
                'maxWidth' => EpaperLayout::MAX_WIDTH,
                'maxColumns' => EpaperLayout::MAX_COLUMNS,
                'minImageHeight' => EpaperLayout::MIN_IMAGE_HEIGHT,
                'maxImageHeight' => EpaperLayout::MAX_IMAGE_HEIGHT,
            ],
            'newsUrl' => route('epaper.news', [], false),
            'publicUrl' => route('frontend.epaper.show', $epaper->edition_date->toDateString(), false),
        ]);
    }

    public function update(Request $request, EpaperPage $epaper): RedirectResponse
    {
        $data = $request->validate(
            [
                'layout' => ['required', 'string'],
                'status' => ['required', Rule::in(array_keys(EpaperPage::STATUS_LABELS))],
            ],
            ['layout.required' => 'পাতা খালি অবস্থায় সেভ করা যায় না।'],
        );

        $plan = $this->planFromJson($data['layout']);
        $this->guardPublished($plan);

        $epaper->update(['layout' => $plan, 'status' => $data['status']]);

        return back()->with('success', $data['status'] === EpaperPage::STATUS_PUBLISHED
            ? 'পাতা সংরক্ষণ ও প্রকাশ হয়েছে।'
            : 'পাতা সংরক্ষিত হয়েছে (খসড়া)।');
    }

    public function destroy(EpaperPage $epaper): RedirectResponse
    {
        $date = $epaper->edition_date->format('d F Y');
        $epaper->delete();

        return redirect()->route('epaper.index')->with('success', $date.' তারিখের পাতা মোছা হয়েছে।');
    }

    /** সংবাদ বাছাইয়ের প্যানেল: প্রকাশিত খবর, খোঁজ ও পাতা। */
    public function news(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        $news = News::published()
            ->with('category')
            ->when($term !== '', fn (Builder $query) => $query->matching($term))
            ->latestPublished()
            ->paginate(24)
            ->withQueryString();

        return response()->json([
            'items' => $news->getCollection()->map(fn (News $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'category' => $item->category->name ?? '',
                'date' => $item->display_date->format('d M Y'),
                'image' => $item->image ? Media::thumbUrl($item->image, 400) : null,
            ])->values(),
            'lastPage' => $news->lastPage(),
            'page' => $news->currentPage(),
        ]);
    }

    private function today(): string
    {
        return now()->timezone('Asia/Dhaka')->toDateString();
    }

    private function planFromJson(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['layout' => 'পাতার তথ্য পড়া যায়নি। পাতা রিলোড করে আবার চেষ্টা করুন।']);
        }

        try {
            return EpaperLayout::sanitize(is_array($decoded) ? $decoded : []);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['layout' => $e->getMessage()]);
        }
    }

    /** ব্লকে শুধুই প্রকাশিত সংবাদ থাকবে; খসড়া/মোছা খবর পেলে সেভ আটকাবে। */
    private function guardPublished(array $plan): void
    {
        $ids = EpaperLayout::newsIds($plan);

        if ($ids === []) {
            return;
        }

        $missing = array_diff($ids, News::published()->whereIn('id', $ids)->pluck('id')->all());

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'layout' => 'যে '.count($missing).'টি সংবাদ এখন প্রকাশিত নেই, সেগুলো পাতা থেকে সরান।',
            ]);
        }
    }

    /** @return array<int, News> id দিয়ে সাজানো */
    private function newsByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return News::withTrashed()->with('category')->whereIn('id', $ids)->get()->keyBy('id')->all();
    }
}
