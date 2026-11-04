@php
    $site = \App\Support\Settings::all();

    // মেনুর সব আইটেম (নিজের লিংক ও ক্যাটাগরি) একসাথে ক্রম অনুযায়ী
    $menuEntries = [];

    foreach (($menuItems ?? collect()) as $menuItem) {
        $menuEntries[] = [
            'serial'   => (int) $menuItem->serial,
            'more'     => (bool) $menuItem->more,
            'item'     => $menuItem,
            'category' => null,
        ];
    }

    foreach ($menuCategories as $menuCategory) {
        $menuEntries[] = [
            'serial'   => (int) $menuCategory->menu_serial,
            'more'     => (bool) $menuCategory->menu_more,
            'item'     => null,
            'category' => $menuCategory,
        ];
    }

    usort($menuEntries, fn ($a, $b) => $a['serial'] <=> $b['serial']);

    $mainEntries = array_values(array_filter($menuEntries, fn ($e) => ! $e['more']));
    $moreEntries = array_values(array_filter($menuEntries, fn ($e) => $e['more']));

    // মেনুর সক্রিয় আইটেম: বিভাগের পেজে সেই বিভাগ, সংবাদ পেজে সংবাদের বিভাগ।
    $activeNews = isset($news) && $news instanceof \App\Models\News ? $news : null;
    $activeSlug = request()->routeIs('frontend.category.show')
        ? (string) request()->route('slug')
        : (string) ($activeNews?->category?->slug ?? '');
    $activeUrl = url()->current();

    $categoryActive = fn ($category) => $activeSlug !== '' && (
        $category->slug === $activeSlug
        || $category->children->contains(fn ($child) => $child->slug === $activeSlug)
    );

    $linkActive = function (string $href) use ($activeUrl) {
        if ($href === '' || str_starts_with($href, '#')) {
            return false;
        }

        $target = str_starts_with($href, 'http://') || str_starts_with($href, 'https://') ? $href : url($href);

        return rtrim($target, '/') === rtrim($activeUrl, '/');
    };

    $moreActive = collect($moreEntries)->contains(fn ($entry) => $entry['item']
        ? $linkActive($entry['item']->href())
        : $categoryActive($entry['category']));

    // ফলাফলের পেজে বক্সে আগের শব্দটা থেকে যায়।
    $searchTerm = is_string(request()->query('q')) ? request()->query('q') : '';
@endphp

<div class="topbar">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                @if($site['show_date'] === '1')
                    {{ now()->timezone('Asia/Dhaka')->format('d F Y, l') }}
                @endif
            </div>
            <div>
                {{ $site['site_name'] }}@if($site['site_tagline'] !== '') | {{ $site['site_tagline'] }}@endif
            </div>
        </div>
    </div>
</div>


<header class="site-header bg-white">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-3 col-md-3 text-center text-md-start">
                <a href="{{ route('home') }}">
                    <img src="{{ \App\Support\Settings::logoUrl() }}"
                         alt="{{ $site['site_name'] }}"
                         class="site-logo img-fluid">
                </a>
            </div>
            <div class="col-lg-9 col-md-9">
                @include('frontend.partials.ad', ['slot' => 'header_top'])
            </div>
        </div>
    </div>
</header>


<nav class="navbar navbar-expand-lg navbar-light navbar-custom">
    <div class="container">

        <button class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#mainMenu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="navbar-collapse collapse visible" id="mainMenu">

            <ul class="navbar-nav flex-wrap justify-content-center sa-nav-list">

                @foreach($mainEntries as $entry)

                    @if($entry['item'])

                        @php $item = $entry['item']; @endphp
                        <li class="nav-item">
                            <a @class(['nav-link', 'active' => $linkActive($item->href())]) href="{{ $item->href() }}" @if($item->new_tab) target="_blank" rel="noopener" @endif>{{ $item->label }}</a>
                        </li>

                    @else

                        @php $category = $entry['category']; @endphp

                        @if($category->slug === \App\Models\Category::NATIONWIDE_SLUG && $divisions->isNotEmpty())
                            <li class="nav-item dropdown">
                                <a @class(['nav-link', 'active' => $categoryActive($category)]) href="{{ route('frontend.category.show', $category->slug) }}">
                                    {{ $category->name }}<span class="sa-nav-caret" aria-hidden="true"></span>
                                </a>
                                <ul class="dropdown-menu">
                                    @foreach($divisions as $division)
                                        <li><a @class(['dropdown-item', 'is-current' => $division->url() === $activeUrl]) href="{{ $division->url() }}">{{ $division->name_bn }}</a></li>
                                    @endforeach
                                    @foreach($category->children as $child)
                                        <li><a @class(['dropdown-item', 'is-current' => $child->slug === $activeSlug]) href="{{ route('frontend.category.show', $child->slug) }}">{{ $child->name }}</a></li>
                                    @endforeach
                                </ul>
                            </li>
                        @elseif($category->children->isNotEmpty())
                            <li class="nav-item dropdown">
                                <a @class(['nav-link', 'active' => $categoryActive($category)]) href="{{ route('frontend.category.show', $category->slug) }}">
                                    {{ $category->name }}<span class="sa-nav-caret" aria-hidden="true"></span>
                                </a>
                                <ul class="dropdown-menu">
                                    <li><span class="sa-mega-title">সব উপ-বিভাগ</span></li>
                                    @foreach($category->children as $child)
                                        <li><a @class(['dropdown-item', 'is-current' => $child->slug === $activeSlug]) href="{{ route('frontend.category.show', $child->slug) }}">{{ $child->name }}</a></li>
                                    @endforeach
                                </ul>
                            </li>
                        @else
                            <li class="nav-item">
                                <a @class(['nav-link', 'active' => $categoryActive($category)]) href="{{ route('frontend.category.show', $category->slug) }}">{{ $category->name }}</a>
                            </li>
                        @endif

                    @endif

                @endforeach

                @if(! empty($moreEntries))
                    <li class="nav-item dropdown">
                        <a @class(['nav-link dropdown-toggle', 'active' => $moreActive]) href="#" data-bs-toggle="dropdown">{{ $site['menu_more_label'] }}</a>
                        <ul class="dropdown-menu">
                            @foreach($moreEntries as $entry)

                                @if($entry['item'])
                                    @php $item = $entry['item']; @endphp
                                    <li>
                                        <a @class(['dropdown-item', 'is-current' => $linkActive($item->href())]) href="{{ $item->href() }}" @if($item->new_tab) target="_blank" rel="noopener" @endif>{{ $item->label }}</a>
                                    </li>
                                @else
                                    @php $category = $entry['category']; @endphp
                                    <li><a @class(['dropdown-item', 'is-current' => $category->slug === $activeSlug]) href="{{ route('frontend.category.show', $category->slug) }}">{{ $category->name }}</a></li>
                                    @foreach($category->children as $child)
                                        <li><a @class(['dropdown-item ps-4', 'is-current' => $child->slug === $activeSlug]) href="{{ route('frontend.category.show', $child->slug) }}">– {{ $child->name }}</a></li>
                                    @endforeach
                                @endif

                            @endforeach
                        </ul>
                    </li>
                @endif

            </ul>

            <form @class(['sa-search', 'sa-search-bar', 'sa-search-filled' => $searchTerm !== ''])
                  action="{{ route('frontend.search') }}"
                  method="get"
                  role="search">
                <label class="sa-search-open" for="saSiteSearch">
                    <span class="visually-hidden">খুঁজুন</span>
                    <svg class="sa-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle>
                        <line x1="16.5" y1="16.5" x2="21" y2="21"></line>
                    </svg>
                </label>
                <input class="sa-search-input"
                       id="saSiteSearch"
                       type="search"
                       name="q"
                       value="{{ $searchTerm }}"
                       placeholder="খুঁজুন...">
                <button class="sa-search-btn" type="submit">
                    <svg class="sa-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle>
                        <line x1="16.5" y1="16.5" x2="21" y2="21"></line>
                    </svg>
                    <span>খুঁজুন</span>
                </button>
            </form>

        </div>
    </div>
</nav>


@php
    $tickerOn = \App\Support\Settings::get('ticker_show') === '1';
    $maxHours = (int) \App\Support\Settings::get('ticker_max_hours');

    $tickerItems = ($breakingNews ?? collect())
        ->filter(fn ($n) => $maxHours <= 0 || $n->display_date->gte(now()->subHours($maxHours)))
        ->take(max(1, (int) \App\Support\Settings::get('ticker_count')));

    $tickerSeconds = ['slow' => 70, 'normal' => 45, 'fast' => 25][\App\Support\Settings::get('ticker_speed')] ?? 45;
@endphp

@if($tickerOn && $tickerItems->isNotEmpty())
<div class="container mt-3">
    <div class="sa-ticker">
        <div class="sa-ticker-label">{{ \App\Support\Settings::get('ticker_label') }}</div>
        <div class="sa-ticker-viewport">
            <div class="sa-ticker-track" style="animation-duration: {{ $tickerSeconds }}s">
                @foreach($tickerItems as $item)
                    <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-ticker-link">{{ $item->title }}</a>
                    <span class="sa-ticker-dot">●</span>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endif