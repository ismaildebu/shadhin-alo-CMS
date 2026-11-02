@extends('frontend.layouts.app')

@section('title', ($news->seo_title ?: $news->title) . ' | ' . \App\Support\Settings::get('site_name'))

@section('meta')
    @php
        $shareImage = $news->card_url ?: ($news->image ? asset('uploads/news/' . $news->image) : null);
    @endphp

    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($news->summary ?: $news->description), 160) }}">
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $news->title }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:site_name" content="{{ \App\Support\Settings::get('site_name') }}">


    @if($shareImage)
        <meta property="og:image" content="{{ $shareImage }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:image" content="{{ $shareImage }}">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600&family=Noto+Serif+Bengali:wght@500;600;700;800&display=swap"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="{{ asset('css/article-show.css') }}">
    <link rel="stylesheet" href="{{ asset('css/article-visuals.css') }}">
    <link rel="stylesheet" href="{{ asset('css/news-fallback.css') }}">

    @include('partials.chart-scripts')

    {{-- Dashboard design values. Built only from whitelisted options and validated hex colors. --}}
    <style>{!! $design['css'] !!}</style>

       @if($design['toggles']['autoload'] && $news->category_id)
        <script src="{{ asset('js/article-autoload.js') }}" defer></script>
        <script src="{{ asset('js/article-view-count.js') }}" defer></script>
    @endif
@endsection

@section('content')


    <!-- 🎧 অডিও প্লেয়ারটি এখানে বসিয়ে দিন -->
    <x-audio-player :news="$news" />

@php
    $by = \App\Support\Avatars::byline($news);
@endphp

    @php
        // Dashboard-controlled design (see App\Support\ArticleDesign).
        $show = $design['toggles'];
        $sideLeft = $design['opt']['side'] === 'left';

        // Item counts of the main-sidebar widgets come from the main sidebar settings.
        $mainWidgets = \App\Support\FrontendCache::shared()['sidebarWidgets']->keyBy('name');
        $widgetLimit = fn (string $name) => $mainWidgets->get($name)?->item_limit
            ?? (\App\Models\SidebarWidget::META[$name]['limit']['default'] ?? null);
    @endphp


    @if($show['no_image_card'])
        @include('frontend.partials.bd-map-defs')
    @endif

    @if($show['breadcrumb'])
        <nav aria-label="breadcrumb" class="sa-breadcrumb-wrap">
            <ol class="breadcrumb sa-breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('home') }}">হোম</a>
                </li>

                @if($news->category)
                    <li class="breadcrumb-item">
                        <a href="{{ route('frontend.category.show', $news->category->slug) }}">
                            {{ $news->category->name }}
                        </a>
                    </li>
                @endif

                <li class="breadcrumb-item active" aria-current="page">
                    {{ \Illuminate\Support\Str::limit($news->title, 50) }}
                </li>
            </ol>
        </nav>
    @endif

    <div class="row g-4 sa-article-layout">
        <div class="{{ $design['main_col'] }}">
            @include('frontend.partials.article-full', [
                'news' => $news,
                'design' => $design,
                'titleTag' => 'h1',
                'withAds' => true,
            ])

            @if($show['related_bottom'] && $relatedNews->isNotEmpty())
                {{-- Without a sidebar this block stays visible on desktop too. --}}
                <section class="sa-related-mobile @unless($design['side_col']) sa-related-always @endunless">
                    <h2 class="sa-section-title">সম্পর্কিত সংবাদ</h2>

                    <div class="sa-related-grid">
                        @foreach($relatedNews->take(4) as $item)
                            <a
                                href="{{ route('frontend.news.show', $item->slug) }}"
                                class="sa-related-card"
                            >
                                @if($item->image)
                                    <img
                                        src="{{ asset('uploads/news/' . $item->image) }}"
                                        alt="{{ $item->title }}"
                                        loading="lazy"
                                    >
                                @endif

                                <span>{{ $item->title }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Next articles of the same category are inserted just before this element. --}}
            @if($show['autoload'] && $news->category_id)
                <div
                    id="sa-autoload"
                    class="sa-autoload"
                    data-slug="{{ $news->slug }}"
                    data-endpoint="{{ route('frontend.news.next', '__SLUG__') }}"
                    data-view="{{ route('frontend.news.view', '__SLUG__') }}"
                    data-max="{{ $design['counts']['autoload'] }}"
                    data-loading="পরের খবর লোড হচ্ছে…"
                    data-error="পরের খবর আনা যায়নি।"
                    data-retry="আবার চেষ্টা করুন"
                >
                    <p class="sa-autoload-status" data-status role="status" aria-live="polite"></p>
                </div>
            @endif
        </div>

        @if($design['side_col'])
            <aside
                class="{{ $design['side_col'] }} sa-article-sidebar @if($sideLeft) order-lg-first @endif"
                aria-label="সংবাদ ও বিজ্ঞাপন"
            >
                <div class="sa-sticky">
                    @foreach($design['sidebar'] as $widget)
                        @switch($widget)
                            @case('ad_top')
                            @case('ad_middle')
                            @case('ad_bottom')
                                @include('frontend.partials.ad', ['slot' => \App\Support\ArticleDesign::WIDGETS[$widget]['slot']])
                                @break

                            @case('latest')
                                @include('frontend.partials.article-list', [
                                    'title' => 'সর্বশেষ সংবাদ',
                                    'items' => $latestNews ?? collect(),
                                ])
                                @break

                            @case('related')
                                @include('frontend.partials.article-list', [
                                    'title' => 'সম্পর্কিত সংবাদ',
                                    'items' => $relatedNews,
                                ])
                                @break

                            @case('tabs')
                            @case('videos')
                            @case('facebook')
                            @case('poll')
                                {{-- Same widget files as the main sidebar. A broken widget is logged and skipped, it never breaks the article. --}}
                                @php
                                    try {
                                        $widgetHtml = view('frontend.partials.widgets.' . $widget, [
                                            'limit' => $widgetLimit($widget),
                                            'latestNews' => $latestNews ?? collect(),
                                            'popularNews' => $popularNews ?? collect(),
                                            'videos' => $videos ?? collect(),
                                            'popularTags' => $popularTags ?? collect(),
                                        ])->render();
                                    } catch (\Throwable $e) {
                                        report($e);
                                        $widgetHtml = '';
                                    }
                                @endphp
                                {!! $widgetHtml !!}
                                @break

                            @case('tags')
                                @if(isset($popularTags) && $popularTags->isNotEmpty())
                                    <section class="sa-widget">
                                        <h2 class="sa-widget-head">জনপ্রিয় ট্যাগ</h2>

                                        <div class="p-3">
                                            @foreach($popularTags->take($design['counts']['tags']) as $popularTag)
                                                <a
                                                    href="{{ route('frontend.tag.show', $popularTag->slug) }}"
                                                    class="sa-tag"
                                                >{{ $popularTag->name }}</a>
                                            @endforeach
                                        </div>
                                    </section>
                                @endif
                                        @include('frontend.partials.author-box', ['by' => $by])
                                @break
                        @endswitch
                    @endforeach
                </div>
            </aside>
        @endif
    </div>
@endsection
