<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\FrontendCache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NewsCardController extends Controller
{
    public const DIR = 'uploads/cards';
        /** কার্ডের ডিজাইন: কী => লেবেল। নতুন ডিজাইন যোগ করলে এখানে ও admin-photocard.js এ যোগ করতে হয়। */
        public const TEMPLATES = [
        'top' => 'ওপরে শিরোনাম, নিচে ছবি (পোর্ট্রেটে ভালো)',
        'quote' => 'উদ্ধৃতি কার্ড, ব্যক্তির ছবিসহ (পোর্ট্রেটে ভালো)',
        'cream' => 'ক্রিম পটভূমিতে লেখা ও সারাংশ (পোর্ট্রেটে ভালো)',
        'column' => 'কলাম কার্ড, লেখকের গোল ছবিসহ (পোর্ট্রেটে ভালো)',
        'brand' => 'ব্র্যান্ড ফ্রেম, সোশ্যাল হ্যান্ডেল ও ফিতা (পোর্ট্রেটে ভালো)',
        'overlay' => 'ছবির ওপর লেখা',
        'split' => 'ওপরে ছবি, নিচে লেখা',
        'bar' => 'সাদা ফলকে লেখা',
        'center' => 'মাঝখানে লেখা (বক্তব্য)',
        'frame' => 'সাদা ফ্রেমে ছবি',
        'side' => 'পাশে রঙিন প্যানেল',
        'breaking' => 'ব্রেকিং নিউজ',
        'slant' => 'তেরছা রঙিন ব্যান্ড',
        'text' => 'শুধু লেখা (ছবি ছাড়া)',
    ];

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
            'footerFallback' => $this->footerText(),
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
                'image' => $n->image ? '/uploads/news/'.$n->image : null,
                'storeUrl' => route('news.card.store', $n, false),
                'excerpt' => $this->plain($n->summary ?: $n->description, 280),
                'caption' => $this->caption($n),
                'byline' => (string) $n->reporter,
            ])->values(),
            'logo' => $this->path(Settings::logoUrl()),
            'emblem' => $this->emblem(),
            'siteName' => Settings::get('site_name'),
            'tagline' => Settings::get('site_tagline'),
            'footer' => $this->footerText(),
            'social' => $this->social(),
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
        return [
            'id' => $news->id,
            'title' => $news->title,
            'category' => $news->category->name ?? '',
            'color' => $news->category?->home_color,
            'date' => $news->display_date->format('d M Y'),
            'image' => $news->image ? '/uploads/news/'.$news->image : null,
            'logo' => $this->path(Settings::logoUrl()),
            'emblem' => $this->emblem(),
            'siteName' => Settings::get('site_name'),
            'tagline' => Settings::get('site_tagline'),
            'footer' => $this->footerText(),
            'excerpt' => $this->plain($news->summary ?: $news->description, 280),
            'caption' => $this->caption($news),
            'byline' => (string) $news->reporter,
            'social' => $this->social(),
            'card' => $news->card_image ? '/'.self::DIR.'/'.$news->card_image : null,
            'storeUrl' => route('news.card.store', $news, false),
            'deleteUrl' => route('news.card.destroy', $news, false),
        ];
    }

    private function currentDefaults(): array
    {
        return [
            'template' => Settings::get('card_template'),
            'size' => Settings::get('card_size'),
            'logo' => Settings::get('card_logo') === '1',
            'category' => Settings::get('card_category') === '1',
            'date' => Settings::get('card_date') === '1',
            'accent' => Settings::get('card_accent'),
            'footer' => Settings::get('card_footer'),
        ];
    }

    /** কার্ডের নিচে ডানে যে লেখা বসবে: সেটিংয়ের লেখা, না থাকলে সাইটের ডোমেইন। */
    private function footerText(): string
    {
        $custom = Settings::get('card_footer');

        if ($custom !== '') {
            return $custom;
        }

        return (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?? '');
    }

    /** পূর্ণ ঠিকানা থেকে শুধু পথ (একই উৎসের ছবি, ক্যানভাস নিরাপদ থাকে)। */
    private function path(string $url): string
    {
        return parse_url($url, PHP_URL_PATH) ?: $url;
    }

    /** HTML থেকে সাধারণ লেখা: ট্যাগ বাদ, সংক্ষিপ্ত করা। */
    private function plain(?string $html, int $limit): string
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return Str::limit(trim((string) preg_replace('/\s+/u', ' ', $text)), $limit, '…');
    }

    private function caption(News $news): string
    {
        $caption = trim((string) $news->image_caption);
        $credit = trim((string) $news->image_credit);

        return trim($caption.($credit !== '' ? ' (ছবি: '.$credit.')' : ''));
    }

    /** গোল প্রতীকের জন্য ফেভিকন (সেট করা থাকলে)। */
    private function emblem(): ?string
    {
        $url = Settings::faviconUrl();

        return $url ? $this->path($url) : null;
    }

    /**
     * সাইট সেটিংয়ের সোশ্যাল লিংক থেকে হ্যান্ডেলের নাম (যেমন facebook.com/abc হলে "abc")।
     *
     * @return list<array{type: string, text: string}>
     */
    private function social(): array
    {
        $items = [];

        foreach (['facebook' => 'social_facebook', 'instagram' => 'social_instagram', 'youtube' => 'social_youtube', 'x' => 'social_x'] as $type => $key) {
            $url = Settings::get($key);

            if ($url === '') {
                continue;
            }

            $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
            $handle = ltrim(Str::afterLast($path, '/'), '@');

            $items[] = [
                'type' => $type,
                'text' => $handle !== '' ? $handle : (string) parse_url($url, PHP_URL_HOST),
            ];
        }

        return $items;
    }

    private function deleteFile(?string $name): void
    {
        if ($name) {
            File::delete(public_path(self::DIR.'/'.basename($name)));
        }
    }
}