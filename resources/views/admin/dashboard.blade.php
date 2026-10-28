@php
    $user = Auth::user();
    $isEditor = $user->isEditorOrAdmin();
    $mine = $isEditor ? '' : ' (আমার)';

    $hour = now()->timezone('Asia/Dhaka')->hour;
    $greeting = $hour < 12 ? 'সুপ্রভাত' : ($hour < 17 ? 'শুভ অপরাহ্ন' : 'শুভ সন্ধ্যা');

    $eye = '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>';

    $stats = [
        ['label' => $isEditor ? 'মোট সংবাদ' : 'আমার সংবাদ', 'value' => $newsTotal, 'url' => route('news.index'), 'tone' => 'blue',
         'icon' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>'],
        ['label' => 'প্রকাশিত', 'value' => $published, 'url' => route('news.index', ['status' => 'published']), 'tone' => 'green',
         'icon' => '<circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/>'],
        ['label' => 'খসড়া', 'value' => $drafts, 'url' => route('news.index', ['status' => 'draft']), 'tone' => 'red',
         'icon' => '<path d="M4 20h4L19 9l-4-4L4 16v4z"/>'],
        ['label' => 'পর্যালোচনার অপেক্ষায়', 'value' => $pending, 'url' => route('news.index', ['status' => 'pending']), 'tone' => 'amber',
         'icon' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'],
        ['label' => 'নির্ধারিত', 'value' => $scheduled, 'url' => route('news.index', ['status' => 'scheduled']), 'tone' => 'sky',
         'icon' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>'],
        ['label' => 'আজকের পাঠ'.$mine, 'value' => number_format($viewsToday), 'url' => route('news.index'), 'tone' => 'teal', 'icon' => $eye],
        ['label' => 'গত ৭ দিনের পাঠ'.$mine, 'value' => number_format($viewsWeek), 'url' => route('news.index'), 'tone' => 'purple', 'icon' => $eye],
        ['label' => 'মোট পাঠ'.$mine, 'value' => number_format($viewsTotal), 'url' => route('news.index'), 'tone' => 'blue', 'icon' => $eye],
    ];

    if ($user->can('manage-categories')) {
        $stats[] = ['label' => 'মোট ক্যাটাগরি', 'value' => $categoriesCount, 'url' => route('categories.index'), 'tone' => 'teal',
            'icon' => '<path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>'];
    }

    if ($usersCount !== null) {
        $stats[] = ['label' => 'মোট ইউজার', 'value' => $usersCount, 'url' => route('users.index'), 'tone' => 'purple',
            'icon' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/>'];
    }

    $maxCat = max(1, (int) $topCategories->max('news_count'));
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ড্যাসবোর্ড</h2>
    </x-slot>

    <div class="ad-page">

        {{-- Welcome --}}
        <div class="ad-hero">
            <div>
                <h1>{{ $greeting }}, {{ $user->name }}</h1>
                <p>
                    {{ \App\Models\User::ROLES[$user->role] ?? $user->role }}
                    &nbsp;|&nbsp;
                    {{ now()->timezone('Asia/Dhaka')->format('d F Y, l') }}
                </p>
            </div>
            <a href="{{ route('news.create') }}" class="ad-btn">
                <svg class="ad-ico" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                নতুন সংবাদ লিখুন
            </a>
        </div>

        {{-- Stats --}}
        <div class="ad-stats">
            @foreach($stats as $s)
                <a href="{{ $s['url'] }}" class="ad-stat">
                    <span class="ad-stat-ico tone-{{ $s['tone'] }}">
                        <svg class="ad-ico" viewBox="0 0 24 24">{!! $s['icon'] !!}</svg>
                    </span>
                    <span>
                        <div class="ad-stat-value">{{ $s['value'] }}</div>
                        <div class="ad-stat-label">{{ $s['label'] }}</div>
                    </span>
                </a>
            @endforeach
        </div>

        <div class="ad-grid">

            {{-- Left column --}}
            <div class="ad-col">

                <div class="ad-panel">
                    <div class="ad-panel-head">
                        <h2>গত ৭ দিনে পাঠ{{ $mine }}</h2>
                        <span class="ad-panel-note">মোট {{ number_format($viewsWeek) }}</span>
                    </div>
                    <div class="ad-panel-body">
                        <div class="ad-chart">
                            @foreach($viewChart as $d)
                                <div class="ad-bar-col">
                                    <span class="ad-bar-num">{{ $d['count'] }}</span>
                                    <div class="ad-bar {{ $d['count'] ? '' : 'is-zero' }}"
                                         style="height:{{ $d['count'] ? max(8, (int) round($d['count'] / $viewChartMax * 150)) : 4 }}px"></div>
                                    <span class="ad-bar-label">{{ $d['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                        @if($viewsWeek === 0)
                            <p class="ad-hint" style="margin-top:12px;">এখনো কোনো পাঠ গোনা হয়নি। পাবলিক খবরের পেজ (লগইন ছাড়া) খুললে গোনা শুরু হবে।</p>
                        @endif
                    </div>
                </div>

                <div class="ad-panel">
                    <div class="ad-panel-head">
                        <h2>গত ৭ দিনে প্রকাশিত</h2>
                        <span class="ad-panel-note">মোট {{ $chartTotal }}টি</span>
                    </div>
                    <div class="ad-panel-body">
                        <div class="ad-chart">
                            @foreach($chart as $d)
                                <div class="ad-bar-col">
                                    <span class="ad-bar-num">{{ $d['count'] }}</span>
                                    <div class="ad-bar {{ $d['count'] ? '' : 'is-zero' }}"
                                         style="height:{{ $d['count'] ? max(8, (int) round($d['count'] / $chartMax * 150)) : 4 }}px"></div>
                                    <span class="ad-bar-label">{{ $d['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="ad-panel">
                    <div class="ad-panel-head">
                        <h2>সাম্প্রতিক সংবাদ</h2>
                        <a href="{{ route('news.index') }}">সব দেখুন »</a>
                    </div>
                    @forelse($recentNews as $item)
                        <a href="{{ route('news.edit', $item) }}" class="ad-row">
                            <div class="ad-row-main">
                                <div class="ad-row-title">{{ $item->title }}</div>
                                <div class="ad-row-sub">
                                    {{ $item->category->name ?? 'ক্যাটাগরি নেই' }}
                                    &middot;
                                    {{ $item->updated_at->format('d M, h:i A') }}
                                    &middot;
                                    পঠিত {{ number_format((int) $item->view_count) }}
                                </div>
                            </div>
                            <span class="ad-badge st-{{ $item->status }}">{{ $item->status_label }}</span>
                        </a>
                    @empty
                        <div class="ad-empty">এখনো কোনো সংবাদ নেই। প্রথম সংবাদটি লিখে ফেলুন।</div>
                    @endforelse
                </div>

            </div>

            {{-- Right column --}}
            <div class="ad-col">

                <div class="ad-panel">
                    <div class="ad-panel-head">
                        <h2>সর্বাধিক পঠিত (৭ দিন){{ $mine }}</h2>
                    </div>
                    @forelse($topRead as $row)
                        <a href="{{ route('news.edit', $row['id']) }}" class="ad-row">
                            <div class="ad-row-main">
                                <div class="ad-row-title">{{ $row['title'] }}</div>
                                <div class="ad-row-sub">{{ $row['category'] }}</div>
                            </div>
                            <span class="ad-badge st-scheduled">{{ number_format($row['views']) }}</span>
                        </a>
                    @empty
                        <div class="ad-empty">এই সপ্তাহে এখনো কোনো পাঠ গোনা হয়নি।</div>
                    @endforelse
                </div>

                @if($isEditor)
                    <div class="ad-panel">
                        <div class="ad-panel-head">
                            <h2>অনুমোদনের অপেক্ষায়</h2>
                            <a href="{{ route('news.index', ['status' => 'pending']) }}">সব »</a>
                        </div>
                        @forelse($pendingNews as $item)
                            <a href="{{ route('news.edit', $item) }}" class="ad-row">
                                <div class="ad-row-main">
                                    <div class="ad-row-title">{{ $item->title }}</div>
                                    <div class="ad-row-sub">
                                        {{ $item->author->name ?? 'অজানা' }}
                                        &middot;
                                        {{ $item->category->name ?? '—' }}
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="ad-empty">পর্যালোচনার জন্য কোনো সংবাদ জমা নেই।</div>
                        @endforelse
                    </div>

                    @if($topCategories->isNotEmpty())
                        <div class="ad-panel">
                            <div class="ad-panel-head">
                                <h2>সবচেয়ে সক্রিয় ক্যাটাগরি</h2>
                            </div>
                            <div style="padding:8px 0;">
                                @foreach($topCategories as $cat)
                                    <div class="ad-meter">
                                        <div class="ad-meter-top">
                                            <span>{{ $cat->name }}</span>
                                            <strong>{{ $cat->news_count }}</strong>
                                        </div>
                                        <div class="ad-meter-track">
                                            <div class="ad-meter-fill" style="width:{{ round($cat->news_count / $maxCat * 100) }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif

                <div class="ad-panel">
                    <div class="ad-panel-head"><h2>দ্রুত কাজ</h2></div>
                    <div class="ad-panel-body">
                        <div class="ad-quick">
                            <a href="{{ route('news.create') }}">
                                <svg class="ad-ico" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                                নতুন সংবাদ
                            </a>
                            @can('manage-categories')
                                <a href="{{ route('categories.index') }}">
                                    <svg class="ad-ico" viewBox="0 0 24 24"><path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/></svg>
                                    ক্যাটাগরি
                                </a>
                            @endcan
                            @can('manage-tags')
                                <a href="{{ route('tags.index') }}">
                                    <svg class="ad-ico" viewBox="0 0 24 24"><path d="M3 12V4h8l10 10-8 8L3 12z"/><circle cx="7.5" cy="8.5" r="1.2"/></svg>
                                    ট্যাগ
                                </a>
                            @endcan
                            @can('manage-users')
                                <a href="{{ route('users.index') }}">
                                    <svg class="ad-ico" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/></svg>
                                    ইউজার
                                </a>
                                <a href="{{ route('activity.index') }}">
                                    <svg class="ad-ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                    কার্যকলাপ
                                </a>
                            @endcan
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>
</x-app-layout>