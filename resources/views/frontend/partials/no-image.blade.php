@php
    /*
     * Placeholder for a news item without an image: logo, category, location text and the
     * division map with the news item's division highlighted.
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
    $mapKey = $place['key'];
    $placeText = $place['text'];

    $label = trim(($news->category->name ?? '') . ($placeText !== '' ? ' · ' . $placeText : ''));
@endphp

<span class="sa-noimg sa-noimg-{{ $variant }} {{ $sizeClass }}" role="img" aria-label="{{ $label !== '' ? $label : 'বাংলাদেশ' }}">
    <span class="sa-noimg-copy">
        <span class="sa-noimg-logo">
            <img src="{{ \App\Support\Settings::logoUrl() }}" alt="" loading="lazy">
        </span>

        <span class="sa-noimg-info">
            @if($news->category)
                <span class="sa-noimg-cat">{{ $news->category->name }}</span>
            @endif

            @if($placeText !== '')
                <span class="sa-noimg-place">{{ $placeText }}</span>
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
</span>
