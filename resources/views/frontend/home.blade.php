@extends('frontend.layouts.app')

@section('content')

@php
    // সব সেকশনের সেটিং আসে অ্যাডমিনের "হোমপেজ সাজান" পেজ থেকে।
    // কোনো ক্যাটাগরিতে স্টাইল বা রং ঠিক করা না থাকলে নিচের তালিকা থেকে পালা করে বসে।
    $validLayouts = array_keys(\App\Models\Category::HOME_LAYOUTS);
    $cycleLayouts = ['split', 'grid4', 'mosaic', 'numbered', 'scroll', 'trio', 'wide', 'region', 'dark', 'opinion'];
    $cycleColors  = ['#d60000', '#123b68', '#0f766e', '#b45309', '#7e22ce', '#0369a1', '#be185d', '#15803d', '#c2410c', '#475569'];

    $systemSections = $homeSections ?? collect();
    $entries = [];

    // ১) সিস্টেম সেকশন: লিড, নির্বাচিত, ভিডিও, বিজ্ঞাপন, সর্বশেষ
    foreach ($systemSections as $name => $section) {
        $meta = \App\Models\HomeSection::META[$name] ?? null;

        if (! $meta || ! $section->visible) {
            continue;
        }

        $ad = null;

        if ($name === 'hero' && empty($leadNews)) {
            continue;
        }

        if ($name === 'featured' && $featuredNews->isEmpty()) {
            continue;
        }

        if ($name === 'videos' && ($videos ?? collect())->isEmpty()) {
            continue;
        }

        if ($name === 'ad_middle') {
            $ad = \App\Support\Ads::pick('home_middle');

            if (! $ad) {
                continue;   // চালু বিজ্ঞাপন না থাকলে ফাঁকা সারি বানানো হয় না
            }
        }

        $sysLayout = $section->layout ?: ($meta['default_layout'] ?? null);
        $sysLimit  = $section->item_limit;

        // নতুন ব্লক-স্টাইলে খবরের মোট সংখ্যা স্থির (lead_sides = ৫, mag_mix = ৯ ...)।
        if (\App\Support\HomeLayouts::get($sysLayout)) {
            $sysRule  = \App\Support\HomeLayouts::limitFor($sysLayout, ['label' => '', 'min' => 0, 'max' => 99]);
            $sysLimit = $sysLimit
                ? max($sysRule['min'], min($sysRule['max'], (int) $sysLimit))
                : \App\Support\HomeLayouts::defaultLimit($sysLayout);
        }

        $entries[] = [
            'serial' => (int) $section->serial,
            'full'   => $meta['fixed_full'] || $section->full_width,
            'type'   => 'system',
            'name'   => $name,
            'limit'  => $sysLimit,
            'layout' => $sysLayout,
            'color'  => \App\Support\Colors::isHex($section->color) ? $section->color : null,
            'show_title' => (bool) ($section->show_title ?? true),
            'bg'     => \App\Support\Colors::isHex($section->bg_color) ? $section->bg_color : null,
            'ad'     => $ad,
        ];
    }

    // ২) ক্যাটাগরির সেকশন (খবর আছে এমন)
    $homeCategories = $categories
        ->whereNull('parent_id')
        ->where('show_on_home', true)
        ->sortBy([['home_serial', 'asc'], ['serial', 'asc'], ['id', 'asc']])
        ->values();

    foreach ($homeCategories as $i => $category) {
        $items = $categoryNews[$category->id] ?? collect();

        if ($items->isEmpty()) {
            continue;
        }

        $layout = in_array($category->home_layout, $validLayouts, true)
            ? $category->home_layout
            : $cycleLayouts[$i % count($cycleLayouts)];

        $accent = \App\Support\Colors::isHex($category->home_color)
            ? $category->home_color
            : $cycleColors[$i % count($cycleColors)];

        $full = (bool) $category->home_full;

        // স্টাইলভেদে সীমা আলাদা (যেমন lead_sides-এ ঠিক ৫টি); registry-তে না থাকলে আগের ২–১২।
        $layoutLimit = \App\Support\HomeLayouts::limitFor($layout, ['label' => 'খবর', 'min' => 2, 'max' => 12]);

        $entries[] = [
            'serial' => (int) $category->home_serial,
            'full'   => $full,
            'type'   => 'category',
            'block'  => [
                'category' => $category,
                'items'    => $items,
                'layout'   => $layout,
                'accent'   => $accent,
                'show_title' => (bool) ($category->home_show_title ?? true),
                'full'     => $full,
                'limit'    => $category->home_limit
                    ? max($layoutLimit['min'], min($layoutLimit['max'], (int) $category->home_limit))
                    : \App\Support\HomeLayouts::defaultLimit($layout),
                'bg'       => \App\Support\Colors::isHex($category->home_bg) ? $category->home_bg : null,
            ],
        ];
    }

    // ৩) সবকিছু ক্রম অনুযায়ী সাজানো (সমান ক্রমে আগে সিস্টেম সেকশন, তারপর ক্যাটাগরি)
    usort($entries, fn ($a, $b) => $a['serial'] <=> $b['serial']);

    // ৩.ক) পরপর একই "কলাম" স্টাইলের ক্যাটাগরি এক সারিতে জড়ো করা (thumb_list, img_heads)।
    //      পুরো-চওড়া হলে ৩/৪ কলাম, সাইডবারের পাশে হলে ২ কলাম। সারি ভরলে নতুন সারি শুরু হয়।
    $merged = [];

    foreach ($entries as $entry) {
        $colLayout = $entry['type'] === 'category' ? $entry['block']['layout'] : null;

        if (! \App\Support\HomeLayouts::isColumn($colLayout)) {
            $merged[] = $entry;

            continue;
        }

        $perRow = \App\Support\HomeLayouts::perRow($colLayout, ! $entry['full']);
        $lastKey = array_key_last($merged);

        if (
            $lastKey !== null
            && $merged[$lastKey]['type'] === 'columns'
            && $merged[$lastKey]['layout'] === $colLayout
            && $merged[$lastKey]['full'] === $entry['full']
            && count($merged[$lastKey]['entries']) < $perRow
        ) {
            $merged[$lastKey]['entries'][] = $entry;
        } else {
            $merged[] = [
                'serial'  => $entry['serial'],
                'full'    => $entry['full'],
                'type'    => 'columns',
                'layout'  => $colLayout,
                'per_row' => $perRow,
                'entries' => [$entry],
            ];
        }
    }

    $entries = $merged;

    // ৪) পরপর পুরো-চওড়া নয় এমন সেকশনগুলো একটি দলে (সাইডবারসহ); পুরো-চওড়াগুলো আলাদা সারিতে
    $segments = [];

    foreach ($entries as $entry) {
        $last = array_key_last($segments);

        if (! $entry['full'] && $last !== null && ! $segments[$last]['full']) {
            $segments[$last]['entries'][] = $entry;
        } else {
            $segments[] = ['full' => $entry['full'], 'entries' => [$entry]];
        }
    }

    $rail = -1;   // প্রথম সাইডবারসহ দলে পুরো সাইডবার, পরের দলগুলোয় ছোট সাইডবার
    $tabsWidget = ($sidebarWidgets ?? collect())->firstWhere('name', 'tabs');
    $tabsUsed = $tabsWidget ? min(max(3, min(15, (int) $tabsWidget->item_limit)), $latestNews->count()) : 0;
@endphp

@foreach($segments as $segment)

    @if($segment['full'])

        @foreach($segment['entries'] as $entry)
            @include($entry['type'] === 'columns' ? 'frontend.partials.home-columns' : 'frontend.partials.home-entry', ['entry' => $entry])
        @endforeach

    @else

        @php $rail++; @endphp

        <div class="row g-4">

            <div class="col-lg-8">
                @foreach($segment['entries'] as $entry)
                    @include($entry['type'] === 'columns' ? 'frontend.partials.home-columns' : 'frontend.partials.home-entry', ['entry' => $entry])
                @endforeach
            </div>

            <div class="col-lg-4">
                @if($rail === 0)
                    @include('frontend.partials.sidebar')
                @else
                    @include('frontend.partials.sidebar-compact', ['offset' => $tabsUsed + 5 * ($rail - 1)])
                @endif
            </div>

        </div>

    @endif

@endforeach

@endsection
