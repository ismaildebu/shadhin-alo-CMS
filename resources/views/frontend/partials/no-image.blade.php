@php
    /*
     * Expects: $news (with category and location loaded)
     * Optional: $variant ('hero' for the article page, 'card' for compact lists)
     * Needs frontend.partials.bd-map-defs and css/news-fallback.css on the page.
     */
    $variant = $variant ?? 'hero';

    $places = $news->location ? collect($news->location->path()) : collect();
    $division = $places->first();

    // The map can highlight a division; district and upazila shapes are not available,
    // so those are shown as text.
    $mapKey = $division
        ? \App\Support\BdMap::keyFor($division->slug ?? null, $division->name_en ?? null)
        : null;

    $placeText = $places->pluck('name_bn')->filter()->implode(' › ');
    $label = trim(($news->category->name ?? '') . ($placeText !== '' ? ' · ' . $placeText : ''));
@endphp

<div class="sa-noimg sa-noimg-{{ $variant }}" role="img" aria-label="{{ $label !== '' ? $label : 'বাংলাদেশ' }}">
    <div class="sa-noimg-copy">
        <span class="sa-noimg-logo">
            <img src="{{ \App\Support\Settings::logoUrl() }}" alt="" loading="lazy">
        </span>

        <div>
            @if($news->category)
                <span class="sa-noimg-cat">{{ $news->category->name }}</span>
            @endif

            @if($placeText !== '')
                <p class="sa-noimg-place">{{ $placeText }}</p>
            @endif
        </div>
    </div>

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
</div>
