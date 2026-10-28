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

            <ul class="navbar-nav flex-wrap justify-content-center w-100">

                @foreach($mainEntries as $entry)

                    @if($entry['item'])

                        @php $item = $entry['item']; @endphp
                        <li class="nav-item">
                            <a class="nav-link" href="{{ $item->href() }}" @if($item->new_tab) target="_blank" rel="noopener" @endif>{{ $item->label }}</a>
                        </li>

                    @else

                        @php $category = $entry['category']; @endphp

                        @if($category->slug === \App\Models\Category::NATIONWIDE_SLUG && $divisions->isNotEmpty())
                            <li class="nav-item dropdown">
                                <a class="nav-link" href="{{ route('frontend.category.show', $category->slug) }}">{{ $category->name }}</a>
                                <ul class="dropdown-menu">
                                    @foreach($divisions as $division)
                                        <li><a class="dropdown-item" href="{{ $division->url() }}">{{ $division->name_bn }}</a></li>
                                    @endforeach
                                    @foreach($category->children as $child)
                                        <li><a class="dropdown-item" href="{{ route('frontend.category.show', $child->slug) }}">{{ $child->name }}</a></li>
                                    @endforeach
                                </ul>
                            </li>
                        @elseif($category->children->isNotEmpty())
                            <li class="nav-item dropdown">
                                <a class="nav-link" href="{{ route('frontend.category.show', $category->slug) }}">{{ $category->name }}</a>
                                <ul class="dropdown-menu">
                                    @foreach($category->children as $child)
                                        <li><a class="dropdown-item" href="{{ route('frontend.category.show', $child->slug) }}">{{ $child->name }}</a></li>
                                    @endforeach
                                </ul>
                            </li>
                        @else
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('frontend.category.show', $category->slug) }}">{{ $category->name }}</a>
                            </li>
                        @endif

                    @endif

                @endforeach

                @if(! empty($moreEntries))
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">{{ $site['menu_more_label'] }}</a>
                        <ul class="dropdown-menu">
                            @foreach($moreEntries as $entry)

                                @if($entry['item'])
                                    @php $item = $entry['item']; @endphp
                                    <li>
                                        <a class="dropdown-item" href="{{ $item->href() }}" @if($item->new_tab) target="_blank" rel="noopener" @endif>{{ $item->label }}</a>
                                    </li>
                                @else
                                    @php $category = $entry['category']; @endphp
                                    <li><a class="dropdown-item" href="{{ route('frontend.category.show', $category->slug) }}">{{ $category->name }}</a></li>
                                    @foreach($category->children as $child)
                                        <li><a class="dropdown-item ps-4" href="{{ route('frontend.category.show', $child->slug) }}">– {{ $child->name }}</a></li>
                                    @endforeach
                                @endif

                            @endforeach
                        </ul>
                    </li>
                @endif

            </ul>

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