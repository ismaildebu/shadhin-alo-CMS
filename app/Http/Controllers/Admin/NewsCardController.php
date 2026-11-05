<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Support\BanglaTime;
use App\Support\FrontendCache;
use App\Support\NewsCard;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NewsCardController extends Controller
{
    public const DIR = NewsCard::DIR;

    /** কার্ডে না-থাকা ডিজাইনের নাম সেটিংয়ে থাকলে যেটিতে ফিরে যায়। */
    public const DEFAULT_TEMPLATE = NewsCard::FALLBACK;

    /** কার্ডের ডিজাইন: কী => লেবেল। তালিকা App\Support\NewsCard এ (JS-এর সঙ্গে একই নাম)। */
    public const TEMPLATES = NewsCard::TEMPLATES;

    /** একটি খবরের কার্ড তৈরির পেজ। */
    public function show(News $news): View
    {
        Gate::authorize('view', $news);

        $news->loadMissing('category');

        return view('admin.news.card', [
            'news' => $news,
            'card' => $this->payload($news),
            'defaults' => $this->currentDefaults(),
            'templates' => self::TEMPLATES,
        ]);
    }

    /** ব্রাউজারে আঁকা কার্ড (JPG) সংরক্ষণ। */
    public function store(Request $request, News $news): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $news);

        $request->validate([
            'card' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:6144'],
        ], [
            'card.required' => 'কার্ডের ছবি পাওয়া যায়নি।',
            'card.mimes' => 'কার্ড jpg বা png হতে হবে।',
            'card.max' => 'কার্ডের ছবি সর্বোচ্চ ৬ MB হতে পারবে।',
            'card.uploaded' => 'আপলোড ব্যর্থ হয়েছে (সার্ভারের আপলোড সীমা কম হতে পারে)।',
        ]);

        $file = $request->file('card');
        $name = $news->id.'-'.Str::uuid().'.'.$file->extension();
        $file->move(public_path(self::DIR), $name);

        $old = $news->card_image;

        // সরাসরি কোয়েরি: খবরের updated_at বা অন্য ইভেন্ট ছোঁয়া হয় না।
        News::whereKey($news->id)->update(['card_image' => $name]);

        // সরাসরি আপডেটে মডেল ইভেন্ট চলে না, তাই ফ্রন্টএন্ড ক্যাশ নিজে পরিষ্কার করতে হয়।
        FrontendCache::flush();

        $this->deleteFile($old);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'url' => '/'.self::DIR.'/'.$name]);
        }

        return back()->with('success', 'ফটোকার্ড সংরক্ষণ হয়েছে।');
    }

    public function destroy(Request $request, News $news): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $news);

        $old = $news->card_image;

        News::whereKey($news->id)->update(['card_image' => null]);

        FrontendCache::flush();

        $this->deleteFile($old);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'ফটোকার্ড মোছা হয়েছে।');
    }

    /** ফটোকার্ড কেন্দ্র (অ্যাডমিন ও এডিটর): ডিফল্ট ডিজাইন ও একসাথে তৈরি। */
    public function center(): View
    {
        return view('admin.photocards', [
            'total' => News::published()->count(),
            'withCard' => News::published()->whereNotNull('card_image')->count(),
            'recent' => News::published()->with('category')->latestPublished()->take(20)->get(),
            'defaults' => $this->currentDefaults(),
            'templates' => self::TEMPLATES,
            'queueUrl' => route('photocards.queue', [], false),
            'footerFallback' => NewsCard::footerText(),
        ]);
    }

    /** কার্ড নেই এমন প্রকাশিত খবরের তালিকা (JSON), কেন্দ্রের "সব তৈরি করুন" বোতামের জন্য। */
    public function queue(): JsonResponse
    {
        $items = News::published()
            ->whereNull('card_image')
            ->with('category')
            ->latestPublished()
            ->take(100)
            ->get();

        return response()->json([
            'items' => $items->map(fn (News $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'category' => $n->category->name ?? '',
                'color' => $n->category?->home_color,
                'date' => $n->display_date->format('d M Y'),
                'dateBn' => BanglaTime::date($n->display_date),
                'image' => $n->image ? '/uploads/news/'.$n->image : null,
                'storeUrl' => route('news.card.store', $n, false),
                'excerpt' => NewsCard::plain($n->summary ?: $n->description, 280),
            ])->values(),
            'logo' => NewsCard::path(Settings::logoUrl()),
            'emblem' => NewsCard::emblem(),
            'siteName' => Settings::get('site_name'),
            'footer' => NewsCard::footerText(),
        ]);
    }

    public function saveDefaults(Request $request): RedirectResponse
    {
        $request->validate([

            'card_template' => ['required', Rule::in(array_keys(self::TEMPLATES))],
            'card_size' => ['required', Rule::in(['landscape', 'square', 'portrait'])],
            'card_accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'card_footer' => ['nullable', 'string', 'max:80'],
        ], [
            'card_accent.regex' => 'রংটি সঠিক নয়।',
        ]);

        Settings::set([
            'card_template' => (string) $request->input('card_template'),
            'card_size' => (string) $request->input('card_size'),
            'card_accent' => (string) $request->input('card_accent'),
            'card_footer' => trim((string) $request->input('card_footer', '')),
            'card_logo' => $request->boolean('card_logo') ? '1' : '0',
            'card_category' => $request->boolean('card_category') ? '1' : '0',
            'card_date' => $request->boolean('card_date') ? '1' : '0',
        ]);

        return back()->with('success', 'ডিফল্ট ডিজাইন সংরক্ষণ হয়েছে।');
    }

    private function payload(News $news): array
    {
        return array_merge(NewsCard::data($news), [
            'id' => $news->id,
            'card' => $news->card_image ? '/'.NewsCard::DIR.'/'.$news->card_image : null,
            'storeUrl' => route('news.card.store', $news, false),
            'deleteUrl' => route('news.card.destroy', $news, false),
        ]);
    }

    private function currentDefaults(): array
    {
        return NewsCard::defaults();
    }

    private function deleteFile(?string $name): void
    {
        if ($name) {
            File::delete(public_path(NewsCard::DIR.'/'.basename($name)));
        }
    }
}
