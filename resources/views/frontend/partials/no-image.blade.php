@php
    /*
     * Placeholder for a news item without an image.
     *   Saradesh news: logo (top right), district / upazila map on the left, division map on the
     *                  right, each with the name of the place; category and place path below.
     *   Other news:    logo (top right) and the category name, no maps.
     *
     * Expects: $news
     * Optional: $sizeClass  classes of the <img> it replaces (keeps the slot's size)
     *           $variant    'hero' | 'card' | 'thumb' (guessed from $sizeClass when omitted)
     * The page needs frontend.partials.bd-map-defs and css/news-fallback.css (the layout has both).
     * Only <span> is used so it is valid inside links and inline containers.
     */
    $sizeClass = $sizeClass ?? '';

    $variant = $variant ?? (preg_match('/thumb|tl-img|lr-img|bt-img|ditem-img/', $sizeClass) ? 'thumb' : 'card');

    $place = \App\Support\NewsFallback::place($news);
    $showMap = \App\Support\NewsFallback::usesMap($news);

    $mapKey = $showMap ? $place['key'] : null;
    $district = ($showMap && $place['geo']) ? \App\Support\BdGeo::render($place['geo']) : null;

    $category = (string) ($news->category->name ?? '');
    $placeText = $showMap ? $place['text'] : '';
    $label = trim($category . ($placeText !== '' ? ' · ' . $placeText : ''));
@endphp

<span class="sa-noimg sa-noimg-{{ $variant }}@if(! $showMap) sa-noimg-plain @endif {{ $sizeClass }}@if($sizeClass === '' && $variant !== 'hero') sa-noimg-dflt @endif" role="img" aria-label="{{ $label !== '' ? $label : 'বাংলাদেশ' }}">
    <span class="sa-noimg-logo">
        <img src="{{ \App\Support\Settings::logoUrl() }}" alt="" loading="lazy">
    </span>

    @if($showMap)
        <span class="sa-noimg-copy">
            @if($district)
                {!! $district['defs'] !!}
                <span class="sa-noimg-district">
                    <span class="sa-noimg-distmap">{!! $district['svg'] !!}</span>

                    @if($place['incident'] !== '')
                        <span class="sa-noimg-dlabel">{{ $place['incident'] }}</span>
                    @endif
                </span>
            @endif

            <span class="sa-noimg-info">
                @if($category !== '')
                    <span class="sa-noimg-cat">{{ $category }}</span>
                @endif

                @if($placeText !== '')
                    <span class="sa-noimg-place">{{ $placeText }}</span>
                @endif

                @if($district && $variant === 'hero')
                    <span class="sa-noimg-credit">সীমানা: {{ \App\Support\BdGeo::attribution() }}</span>
                @endif
            </span>
        </span>

        <svg class="sa-noimg-map" viewBox="{{ \App\Support\BdMap::viewBox() }}" aria-hidden="true" focusable="false">
            @foreach(array_keys(\App\Support\BdMap::SHAPES) as $key)
                @if($key !== $mapKey)
                    <use href="#sa-bd-{{ $key }}" class="sa-noimg-dim"/>
                @endif
            @endforeach

            @if($mapKey)
                <use href="#sa-bd-{{ $mapKey }}" class="sa-noimg-hl"/>
            @endif
        </svg>

        @if($mapKey && $place['division'] !== '')
            <span class="sa-noimg-vlabel">{{ $place['division'] }}</span>
        @endif
    @else
        <span class="sa-noimg-big">{{ $category }}</span>
    @endif
</span>
