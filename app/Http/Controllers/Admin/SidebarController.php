<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SidebarWidget;
use App\Support\Colors;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SidebarController extends Controller
{
    public function edit(): View
    {
        $rows = SidebarWidget::all()
            ->filter(fn ($widget) => isset(SidebarWidget::META[$widget->name]))
            ->map(function ($widget) {
                $meta = SidebarWidget::META[$widget->name];

                return [
                    'key' => $widget->name,
                    'label' => $meta['label'],
                    'note' => $meta['note'],
                    'visible' => $widget->visible,
                    'limit' => $widget->item_limit ?? ($meta['limit']['default'] ?? null),
                    'limit_meta' => $meta['limit'],
                    'layouts' => $meta['layouts'],
                    'layout' => $widget->layout ?: ($meta['default_layout'] ?? ''),
                    'styled' => $meta['styled'],
                    'accent_meta' => $meta['accent'] ?? $meta['styled'],
                    'accent' => $widget->accent,
                    'box_height' => $widget->box_height,
                    'serial' => $widget->serial,
                ];
            })
            ->sortBy('serial')
            ->values();

        return view('admin.sidebar', [
            'rows' => $rows,
            'facebookSet' => Settings::get('social_facebook') !== '',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.serial' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'rows.*.limit' => ['nullable', 'integer', 'min:0', 'max:100'],
            'rows.*.layout' => ['nullable', 'string', 'max:20'],
            'rows.*.accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'rows.*.height' => ['nullable', 'integer', 'min:0', 'max:2000'],
        ], [
            'rows.required' => 'সাইডবারের কোনো তথ্য পাওয়া যায়নি।',
            'rows.*.serial.integer' => 'ক্রম একটি পূর্ণ সংখ্যা হতে হবে।',
            'rows.*.limit.integer' => 'সংখ্যা একটি পূর্ণ সংখ্যা হতে হবে।',
            'rows.*.accent.regex' => 'রংটি সঠিক নয়।',
            'rows.*.height.integer' => 'উচ্চতা একটি পূর্ণ সংখ্যা হতে হবে।',
            'rows.*.height.max' => 'উচ্চতা সর্বোচ্চ ২০০০ পিক্সেল হতে পারবে।',
        ]);

        $rows = $request->input('rows', []);

        foreach (SidebarWidget::all() as $widget) {
            $row = $rows[$widget->name] ?? null;
            $meta = SidebarWidget::META[$widget->name] ?? null;

            if (! $row || ! $meta) {
                continue;
            }

            $limit = null;

            if ($meta['limit']) {
                // সীমার বাইরের সংখ্যা এরর না দিয়ে সীমায় এনে দেওয়া হয়।
                $limit = max($meta['limit']['min'], min($meta['limit']['max'], (int) ($row['limit'] ?? $meta['limit']['default'])));
            }

            $layouts = $meta['layouts'];
            $layout = (string) ($row['layout'] ?? '');
            $heightRaw = trim((string) ($row['height'] ?? ''));
            $accentAllowed = $meta['accent'] ?? $meta['styled'];

            $widget->update([
                'visible' => isset($row['show']),
                'item_limit' => $limit,
                'serial' => (int) ($row['serial'] ?? 0),
                'layout' => $layouts
                    ? (array_key_exists($layout, $layouts) ? $layout : $meta['default_layout'])
                    : null,
                'accent' => ($accentAllowed && isset($row['accent_on']) && Colors::isHex($row['accent'] ?? null))
                    ? $row['accent']
                    : null,
                'box_height' => ($meta['styled'] && $heightRaw !== '') ? (int) $heightRaw : null,
            ]);
        }

        return back()->with('success', 'সাইডবারের সেটিং সংরক্ষণ হয়েছে।');
    }
}