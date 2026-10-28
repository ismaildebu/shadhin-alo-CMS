<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MenuItem;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MenuController extends Controller
{
    private const URL_PATTERN = '~^(https?://|/(?!/))\S+$~u';

    public function edit(): View
    {
        $rows = collect();

        foreach (MenuItem::all() as $item) {
            $rows->push([
                'key' => 'item_'.$item->id,
                'kind' => 'item',
                'id' => $item->id,
                'type' => $item->type,
                'label' => $item->label,
                'url' => $item->url,
                'visible' => $item->visible,
                'more' => $item->more,
                'new_tab' => $item->new_tab,
                'serial' => $item->serial,
                'inactive' => false,
                'children' => 0,
            ]);
        }

        foreach (Category::whereNull('parent_id')->withCount('children')->get() as $category) {
            $rows->push([
                'key' => 'cat_'.$category->id,
                'kind' => 'category',
                'id' => $category->id,
                'type' => 'category',
                'label' => $category->name,
                'url' => null,
                'visible' => $category->show_in_menu,
                'more' => $category->menu_more,
                'new_tab' => false,
                'serial' => $category->menu_serial,
                'inactive' => ! $category->status,
                'children' => $category->children_count,
            ]);
        }

        return view('admin.menu', [
            'rows' => $rows->sortBy('serial')->values(),
            'moreLabel' => Settings::get('menu_more_label'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'more_label' => ['required', 'string', 'max:30'],
            'rows' => ['required', 'array'],
            'rows.*.serial' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'rows.*.label' => ['nullable', 'string', 'max:60'],
            'rows.*.url' => ['nullable', 'string', 'max:500'],
            'rows.*.place' => ['nullable', 'in:main,more'],
        ], [
            'more_label.required' => 'ড্রপডাউনের নাম লিখুন।',
            'rows.required' => 'মেনুর কোনো তথ্য পাওয়া যায়নি।',
            'rows.*.serial.integer' => 'ক্রম একটি পূর্ণ সংখ্যা হতে হবে।',
            'rows.*.label.max' => 'মেনুর নাম সর্বোচ্চ ৬০ অক্ষরের হতে পারবে।',
        ]);

        $rows = $request->input('rows', []);

        // আগে সব যাচাই, তারপর সংরক্ষণ: ভুল থাকলে কিছুই আংশিক সেভ হবে না।
        $itemUpdates = [];

        foreach (MenuItem::all() as $item) {
            $row = $rows['item_'.$item->id] ?? null;

            if (! $row) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));

            if ($label === '') {
                throw ValidationException::withMessages(['rows' => 'মেনুর কোনো আইটেমের নাম খালি রাখা যাবে না।']);
            }

            $data = [
                'label' => $label,
                'visible' => isset($row['show']),
                'more' => ($row['place'] ?? 'main') === 'more',
                'serial' => (int) ($row['serial'] ?? 0),
            ];

            if ($item->type === 'link') {
                $url = trim((string) ($row['url'] ?? ''));

                if (! preg_match(self::URL_PATTERN, $url)) {
                    throw ValidationException::withMessages([
                        'rows' => "‘{$label}’ এর লিংক ঠিক নয়। https://... অথবা / দিয়ে শুরু করুন।",
                    ]);
                }

                $data['url'] = $url;
                $data['new_tab'] = isset($row['new_tab']);
            }

            $itemUpdates[] = [$item, $data];
        }

        foreach ($itemUpdates as [$item, $data]) {
            $item->update($data);
        }

        $ids = collect($rows)
            ->keys()
            ->filter(fn ($key) => str_starts_with((string) $key, 'cat_'))
            ->map(fn ($key) => (int) substr((string) $key, 4))
            ->all();

        foreach (Category::whereNull('parent_id')->whereIn('id', $ids)->get() as $category) {
            $row = $rows['cat_'.$category->id];

            $category->update([
                'show_in_menu' => isset($row['show']),
                'menu_more' => ($row['place'] ?? 'main') === 'more',
                'menu_serial' => (int) ($row['serial'] ?? 0),
            ]);
        }

        Settings::set(['menu_more_label' => trim((string) $request->input('more_label'))]);

        return back()->with('success', 'মেনুর সেটিং সংরক্ষণ হয়েছে।');
    }

    public function storeLink(Request $request): RedirectResponse
    {
        $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'url' => ['required', 'string', 'max:500', 'regex:'.self::URL_PATTERN],
        ], [
            'label.required' => 'নতুন লিংকের একটি নাম দিন।',
            'url.required' => 'লিংক দিন।',
            'url.regex' => 'সঠিক লিংক দিন: https://example.com অথবা /about (/ দিয়ে শুরু করলে নিজের সাইটের পেজ)।',
        ]);

        $serial = max((int) MenuItem::max('serial'), (int) Category::max('menu_serial')) + 10;

        MenuItem::create([
            'type' => 'link',
            'label' => trim((string) $request->input('label')),
            'url' => trim((string) $request->input('url')),
            'new_tab' => $request->boolean('new_tab'),
            'visible' => true,
            'more' => false,
            'serial' => $serial,
        ]);

        return back()->with('success', 'লিংক যোগ হয়েছে। মেনুর শেষে বসেছে, ↑ বোতামে সরিয়ে নিন।');
    }

    public function destroyLink(MenuItem $item): RedirectResponse
    {
        // হোম ও ভিডিও আইটেম মোছা যায় না; তাদের শুধু লুকানো যায়।
        abort_unless($item->type === 'link', 403);

        $item->delete();

        return back()->with('success', 'লিংক মোছা হয়েছে।');
    }
}