<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\HomeSection;
use App\Support\Colors;
use App\Support\HomeLayouts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use LogicException;

class HomeLayoutController extends Controller
{
    /** ক্যাটাগরির ব্লকে কয়টি খবর দেখানো যাবে (ফাঁকা মানে স্টাইলের ডিফল্ট)। */
    private const CATEGORY_LIMIT = ['label' => 'খবর', 'min' => 2, 'max' => 12];

    /**
     * ভিউতে যাওয়া প্রতিটি row-এর নির্দিষ্ট shape।
     * নতুন কী যোগ করতে হলে এখানে ডিফল্টসহ যোগ করুন।
     */
    private const ROW_DEFAULTS = [
        'key' => '',
        'kind' => 'system',
        'label' => '',
        'visible' => false,
        'full' => false,
        'fixed_full' => false,
        'layouts' => [],
        'layout' => '',
        'color' => null,
        'bg' => null,
        'bg_allowed' => true,
        'color_allowed' => false,
        'show_title' => true,
        'title_allowed' => false,
        'limit' => null,
        'limit_meta' => null,
        'serial' => 0,
        'news_count' => null,
        'inactive' => false,
    ];

    /**
     * ভিউতে পাঠানো প্রতিটি row এই মেথড দিয়ে যাবে।
     * অচেনা কী দিলে সঙ্গে সঙ্গে exception হবে (টাইপো ধরা পড়বে),
     * আর অনুপস্থিত কী ডিফল্ট মান পাবে (ভিউতে Undefined array key হবে না)।
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function makeRow(array $attributes): array
    {
        $unknown = array_diff_key($attributes, self::ROW_DEFAULTS);

        if ($unknown !== []) {
            throw new LogicException(
                'Unknown home-layout row keys: '.implode(', ', array_keys($unknown))
            );
        }

        return array_merge(self::ROW_DEFAULTS, $attributes);
    }

    public function edit(): View
    {
        $rows = collect();

        foreach (HomeSection::all() as $section) {
            $meta = HomeSection::META[$section->name] ?? null;

            if (! $meta) {
                continue;
            }

            $fixedFull = (bool) ($meta['fixed_full'] ?? false);
            $sectionLayout = (string) ($section->layout ?: ($meta['default_layout'] ?? ''));

            // নতুন স্টাইলে খবরের সংখ্যা স্টাইল অনুযায়ী স্থির (যেমন lead_sides-এ মোট ৫টি)।
            $limitMeta = $meta['limit'] ?? null;

            if ($limitMeta && HomeLayouts::get($sectionLayout)) {
                $limitMeta = ['label' => 'মোট খবর'] + HomeLayouts::limitFor($sectionLayout, $limitMeta);
            }

            $rows->push($this->makeRow([
                'key' => $section->name,
                'kind' => 'system',
                'label' => $meta['label'] ?? $section->name,
                'visible' => (bool) $section->visible,
                'full' => (bool) $section->full_width || $fixedFull,
                'fixed_full' => $fixedFull,
                'layouts' => $meta['layouts'] ?? [],
                'layout' => $sectionLayout,
                'color' => $section->color,
                'color_allowed' => $section->name !== 'ad_middle',
                'show_title' => (bool) ($section->show_title ?? true),
                'title_allowed' => $section->name !== 'ad_middle',
                'bg' => $section->bg_color,
                'bg_allowed' => $section->name !== 'ad_middle',
                'limit' => $section->item_limit ?? ($limitMeta['default'] ?? null),
                'limit_meta' => $limitMeta,
                'serial' => (int) $section->serial,
                'news_count' => null,
                'inactive' => false,
            ]));
        }

        $categories = Category::whereNull('parent_id')
            ->withCount(['news' => fn ($q) => $q->published()])
            ->orderBy('home_serial')
            ->orderBy('serial')
            ->orderBy('id')
            ->get();

        foreach ($categories as $c) {
            $rows->push($this->makeRow([
                'key' => 'cat_'.$c->id,
                'kind' => 'category',
                'label' => $c->name,
                'visible' => (bool) $c->show_on_home,
                'full' => (bool) $c->home_full,
                'fixed_full' => false,
                'layouts' => Category::HOME_LAYOUTS,
                'layout' => (string) $c->home_layout,
                'color' => $c->home_color,
                'color_allowed' => true,
                'show_title' => (bool) ($c->home_show_title ?? true),
                'title_allowed' => true,
                'bg' => $c->home_bg,
                'bg_allowed' => true,
                'limit' => $c->home_limit,
                // স্টাইলভেদে সীমা আলাদা (যেমন lead_sides-এ ঠিক ৫টি), তাই registry থেকে।
                'limit_meta' => HomeLayouts::limitFor($c->home_layout, self::CATEGORY_LIMIT),
                'serial' => (int) $c->home_serial,
                'news_count' => $c->news_count,
                'inactive' => ! $c->status,
            ]));
        }

        return view('admin.home-layout', [
            'rows' => $rows->sortBy('serial')->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.layout' => ['nullable', 'string', 'max:20'],
            'rows.*.color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'rows.*.bg' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'rows.*.serial' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'rows.*.limit' => ['nullable', 'integer', 'min:0', 'max:100'],
        ], [
            'rows.required' => 'কোনো সেকশনের তথ্য পাওয়া যায়নি।',
            'rows.*.color.regex' => 'একটি রং সঠিক নয়।',
            'rows.*.bg.regex' => 'একটি ব্যাকগ্রাউন্ড রং সঠিক নয়।',
            'rows.*.serial.integer' => 'ক্রম একটি পূর্ণ সংখ্যা হতে হবে।',
            'rows.*.limit.integer' => 'সংখ্যা একটি পূর্ণ সংখ্যা হতে হবে।',
        ]);

        $rows = $request->input('rows', []);

        // সিস্টেম সেকশন (লিড, নির্বাচিত, ভিডিও, বিজ্ঞাপন, সর্বশেষ)
        foreach (HomeSection::all() as $section) {
            $row = $rows[$section->name] ?? null;
            $meta = HomeSection::META[$section->name] ?? null;

            if (! $row || ! $meta) {
                continue;
            }

            $layout = (string) ($row['layout'] ?? '');
            $layouts = $meta['layouts'] ?? [];
            $layoutValue = $layouts
                ? (array_key_exists($layout, $layouts) ? $layout : ($meta['default_layout'] ?? null))
                : null;

            $limit = null;

            if (! empty($meta['limit'])) {
                // সীমার বাইরের সংখ্যা এরর না দিয়ে সীমায় আনা হয়; নতুন স্টাইলের নিজস্ব সীমা থাকলে সেটিই।
                $rule = HomeLayouts::limitFor($layoutValue, $meta['limit']);
                $limit = max($rule['min'], min($rule['max'], (int) ($row['limit'] ?? $rule['default'] ?? $meta['limit']['default'])));
            }

            $section->update([
                'visible' => isset($row['show']),
                'full_width' => ($meta['fixed_full'] ?? false) || isset($row['full']),
                'item_limit' => $limit,
                'serial' => (int) ($row['serial'] ?? 0),
                'layout' => $layoutValue,
                'color' => Colors::isHex($row['color'] ?? null) ? $row['color'] : null,
                // শিরোনামের টিক: ফর্মে title_sent না থাকলে (পুরনো পেজ) শিরোনাম দেখানোই থাকে।
                'show_title' => $section->name === 'ad_middle' || ! isset($row['title_sent']) || isset($row['title']),
                'bg_color' => (isset($row['bg_on']) && Colors::isHex($row['bg'] ?? null)) ? $row['bg'] : null,
            ]);
        }

        // ক্যাটাগরির সেকশন
        $ids = collect($rows)
            ->keys()
            ->filter(fn ($key) => str_starts_with((string) $key, 'cat_'))
            ->map(fn ($key) => (int) substr((string) $key, 4))
            ->all();

        foreach (Category::whereNull('parent_id')->whereIn('id', $ids)->get() as $category) {
            $row = $rows['cat_'.$category->id];

            $layout = (string) ($row['layout'] ?? '');
            $color = (string) ($row['color'] ?? '');
            $limitRaw = trim((string) ($row['limit'] ?? ''));

            $layoutValue = array_key_exists($layout, Category::HOME_LAYOUTS) ? $layout : null;

            // নির্বাচিত স্টাইলের নিজস্ব সীমা (না থাকলে CATEGORY_LIMIT) অনুযায়ী clamp।
            $limitMeta = HomeLayouts::limitFor($layoutValue, self::CATEGORY_LIMIT);

            // save() এ ক্যাটাগরির ক্যাশ নিজে থেকেই পরিষ্কার হয়, তাই পাবলিক সাইটে সঙ্গে সঙ্গে বদলায়।
            $category->update([
                'show_on_home' => isset($row['show']),
                'home_show_title' => ! isset($row['title_sent']) || isset($row['title']),
                'home_full' => isset($row['full']),
                'home_layout' => $layoutValue,
                'home_color' => Colors::isHex($color) ? $color : null,
                'home_bg' => (isset($row['bg_on']) && Colors::isHex($row['bg'] ?? null)) ? $row['bg'] : null,
                'home_limit' => $limitRaw === ''
                    ? null
                    : max($limitMeta['min'], min($limitMeta['max'], (int) $limitRaw)),
                'home_serial' => (int) ($row['serial'] ?? 0),
            ]);
        }

        return back()->with('success', 'হোমপেজের সেটিং সংরক্ষণ হয়েছে।');
    }
}
