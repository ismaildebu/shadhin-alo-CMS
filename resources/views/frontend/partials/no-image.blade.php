@php
    /*
     * Placeholder for a news item without an image.
     *   Saradesh news with a matched district: the map picture — country on the left, the district
     *                  enlarged on the right with a pin and the district name on a teal field.
     *   Saradesh news without one: category, logo, district / upazila map and division map, then
     *                  the place name large with its path.
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

    $scene = ($showMap && $place['geo']) ? \App\Support\BdGeo::scene($place['geo']) : null;

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

    $sceneLabel = $scene
        ? trim($category . ($scene['name'] !== '' ? ' · ' . $scene['name'] : ''), ' ·')
        : $label;

    // the district path is drawn in the geoBoundaries space; move/scale it to the upazila space
    $sx = $scene ? $scene['local'][0] / $scene['box'][2] : 1;
    $sy = $scene ? $scene['local'][1] / $scene['box'][3] : 1;

    $pin = 'M0 0C-3.2-6.5-34-37-34-63A34 34 0 0 1 34-63C34-37 3.2-6.5 0 0Z';
@endphp

@if($chartThumb !== null)
    <span
        class="sa-chart-thumb {{ $sizeClass }}"
        style="background: {{ $chartThumb['background'] }};"
        role="img"
        aria-label="{{ $chartThumb['label'] !== '' ? $chartThumb['label'] : $news->title }}"
    >{!! $chartThumb['svg'] !!}</span>
@elseif($scene !== null)
    {!! $scene['defs'] !!}

    <span
        class="sa-noimg sa-noimg-scene sa-noimg-{{ $variant }} {{ $sizeClass }}@if($sizeClass === '' && $variant !== 'hero') sa-noimg-dflt @endif"
        role="img"
        aria-label="{{ $sceneLabel !== '' ? $sceneLabel : 'বাংলাদেশ' }}"
    >
        @if($variant === 'thumb')
            <svg class="sa-noimg-art" viewBox="{{ $scene['thumb']['viewBox'] }}" preserveAspectRatio="xMidYMid meet" aria-hidden="true" focusable="false">
                <rect x="0" y="0" width="300" height="300" fill="#01878a"/>

                <svg
                    x="{{ $scene['thumb']['x'] }}" y="{{ $scene['thumb']['y'] }}"
                    width="{{ $scene['thumb']['w'] }}" height="{{ $scene['thumb']['h'] }}"
                    viewBox="0 0 {{ $scene['local'][0] }} {{ $scene['local'][1] }}"
                    preserveAspectRatio="none"
                >
                    <path
                        d="{{ $scene['outline'] }}"
                        transform="scale({{ $sx }} {{ $sy }}) translate({{ -$scene['box'][0] }} {{ -$scene['box'][1] }})"
                        class="sa-noimg-outline"
                        stroke-width="{{ $scene['thumb']['stroke']['outline'] }}"
                    />
                </svg>

                <g class="sa-noimg-pin" transform="translate({{ $scene['thumb']['pin']['x'] }} {{ $scene['thumb']['pin']['y'] }}) scale(.64)">
                    <path d="{{ $pin }}" fill="#ffffff"/>
                    <circle cx="0" cy="-62" r="22" fill="#c2132c"/>
                </g>
            </svg>
        @else
            <svg class="sa-noimg-art" viewBox="{{ $scene['viewBox'] }}" preserveAspectRatio="xMidYMid meet" aria-hidden="true" focusable="false">
                <rect x="0" y="0" width="1020" height="568" fill="#01878a"/>

                <svg
                    x="{{ $scene['left']['x'] }}" y="{{ $scene['left']['y'] }}"
                    width="{{ $scene['left']['w'] }}" height="{{ $scene['left']['h'] }}"
                    viewBox="{{ \App\Support\BdMap::viewBox() }}"
                    preserveAspectRatio="none"
                >
                    @foreach(array_keys(\App\Support\BdMap::SHAPES) as $divisionKey)
                        <use href="#sa-bd-{{ $divisionKey }}" class="sa-noimg-cty"/>
                    @endforeach
                </svg>

                <svg
                    x="{{ $scene['right']['x'] }}" y="{{ $scene['right']['y'] }}"
                    width="{{ $scene['right']['w'] }}" height="{{ $scene['right']['h'] }}"
                    viewBox="0 0 {{ $scene['local'][0] }} {{ $scene['local'][1] }}"
                    preserveAspectRatio="none"
                >
                    <path
                        d="{{ $scene['outline'] }}"
                        transform="scale({{ $sx }} {{ $sy }}) translate({{ -$scene['box'][0] }} {{ -$scene['box'][1] }})"
                        class="sa-noimg-outline"
                        stroke-width="{{ $scene['stroke']['outline'] }}"
                    />

                    <g clip-path="url(#sa-gclip{{ $scene['index'] }})">
                        @foreach($scene['shapes'] as $shapeKey => $shape)
                            <use href="#sa-g{{ $scene['index'] }}-{{ $shapeKey }}" class="sa-noimg-upz" stroke-width="{{ $scene['stroke']['inner'] }}"/>
                        @endforeach
                    </g>
                </svg>

                <path class="sa-noimg-link" d="{{ $scene['connector'] }}"/>
                <circle class="sa-noimg-dot" cx="{{ $scene['dot']['x'] }}" cy="{{ $scene['dot']['y'] }}" r="5.6"/>

                <g class="sa-noimg-pin" transform="translate({{ $scene['pin']['x'] }} {{ $scene['pin']['y'] }})">
                    <path d="{{ $pin }}" fill="#ffffff"/>
                    <circle cx="0" cy="-62" r="22" fill="#c2132c"/>
                </g>

                <text
                    class="sa-noimg-label"
                    x="{{ $scene['label']['x'] }}" y="{{ $scene['label']['y'] }}"
                    text-anchor="middle" dominant-baseline="middle"
                    font-size="{{ $scene['label']['size'] }}"
                >{{ $scene['name'] }}</text>

                @if($variant === 'hero')
                    <text class="sa-noimg-credit" x="18" y="552" text-anchor="start">{{ 'সীমানা: '.\App\Support\BdGeo::attribution() }}</text>
                @endif
            </svg>
        @endif
    </span>
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
