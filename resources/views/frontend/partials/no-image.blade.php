@php
    /*
     * Placeholder for a news item without an image.
     *   Saradesh news: category (top left), logo (top right), district / upazila map and division
     *                  map side by side, then the place name large with its path.
     *   Other news:    logo (top right) and the category name large, no maps.
     *
     * Expects: $news
     * Optional: $sizeClass  classes of the <img> it replaces (keeps the slot's size)
     *           $variant    'hero' | 'card' | 'thumb' (guessed from $sizeClass when omitted)
     * The page needs frontend.partials.bd-map-defs and css/news-fallback.css (the layout has both).
     * Only <span> is used so it is valid inside links and inline containers.
     */
    $sizeClass = $sizeClass ?? '';

    $variant = $variant ?? (preg_match('/thumb|tl-img|lr-img|bt-img|ditem-img/', $sizeClass) ? 'thumb' : 'card');

    // ছবি নেই কিন্তু নিউজে গ্রাফ শর্টকোড আছে: একই স্লটে ছোট চার্ট।
    $chartThumb = \App\Support\ArticleVisuals::thumb((string) ($news->chart_shortcode ?? ''));

    $place = \App\Support\NewsFallback::place($news);
    $showMap = \App\Support\NewsFallback::usesMap($news);

    $mapKey = $showMap ? $place['key'] : null;
    $district = ($showMap && $place['geo']) ? \App\Support\BdGeo::render($place['geo']) : null;

    $category = (string) ($news->category->name ?? '');
    $incident = $showMap ? $place['incident'] : '';
    $division = $showMap ? $place['division'] : '';

    // the large name: the place of the event, else the division, else the category
    $name = $incident !== '' ? $incident : ($division !== '' ? $division : $category);

    $kicker = '';

    if ($showMap) {
        $kicker = match ($place['incident_type']) {
            'upazila' => 'উপজেলা',
            'district' => 'জেলা',
            default => $division !== '' ? 'বিভাগ' : '',
        };
    }

    // "Dhaka › Gopalganj": the path above the named place
    $text = $showMap ? $place['text'] : '';
    $path = str_contains($text, ' › ') ? \Illuminate\Support\Str::beforeLast($text, ' › ') : '';

    $label = trim($category . ($text !== '' ? ' · ' . $text : ''));
@endphp

@if($chartThumb !== null)
    <span
        class="sa-chart-thumb {{ $sizeClass }}"
        style="background: {{ $chartThumb['background'] }};"
        role="img"
        aria-label="{{ $chartThumb['label'] !== '' ? $chartThumb['label'] : $news->title }}"
    >{!! $chartThumb['svg'] !!}</span>
@else
<span class="sa-noimg sa-noimg-{{ $variant }}@if(! $showMap) sa-noimg-plain @endif {{ $sizeClass }}@if($sizeClass === '' && $variant !== 'hero') sa-noimg-dflt @endif" role="img" aria-label="{{ $label !== '' ? $label : 'বাংলাদেশ' }}">
    <span class="sa-noimg-top">
        @if($showMap && $category !== '')
            <span class="sa-noimg-cat">{{ $category }}</span>
        @else
            <span></span>
        @endif

        <span class="sa-noimg-logo">
            <img src="{{ \App\Support\Settings::logoUrl() }}" alt="" loading="lazy">
        </span>
    </span>

    @if($showMap)
        <span class="sa-noimg-stage @if(! $district) sa-noimg-stage--solo @endif">
            @if($district)
                {!! $district['defs'] !!}
                <span class="sa-noimg-distmap">{!! $district['svg'] !!}</span>
            @endif

            <span class="sa-noimg-divmap">
                <svg class="sa-noimg-map" viewBox="{{ \App\Support\BdMap::viewBox() }}" preserveAspectRatio="xMidYMid meet" aria-hidden="true" focusable="false">
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
        </span>
    @endif

    <span class="sa-noimg-foot">
        <span class="sa-noimg-name">
            @if($kicker !== '')
                <span class="sa-noimg-kicker">{{ $kicker }}</span>
            @endif

            <span class="sa-noimg-title">{{ $name }}</span>

            @if($path !== '')
                <span class="sa-noimg-path">{{ $path }}</span>
            @endif

            @if($district && $variant === 'hero')
                <span class="sa-noimg-credit">সীমানা: {{ \App\Support\BdGeo::attribution() }}</span>
            @endif
        </span>

        @if($mapKey && $division !== '' && $kicker !== 'বিভাগ')
            <span class="sa-noimg-vlabel">{{ $division }} বিভাগ</span>
        @endif
    </span>
</span>
@endif
