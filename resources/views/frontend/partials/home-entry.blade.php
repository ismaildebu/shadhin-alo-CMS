@php
    $bg = $entry['bg'] ?? null;
    $bgClass = $bg ? ' sa-bg '.(\App\Support\Colors::isDark($bg) ? 'sa-bg-dark' : 'sa-bg-light') : '';
    $views = \App\Models\Category::HOME_VIEWS;
@endphp

@if($entry['type'] === 'category')

    @if(($entry['block']['show_title'] ?? true) === false)
        <div class="sa-nohead">
            @include('frontend.partials.home-block', ['b' => $entry['block']])
        </div>
    @else
        @include('frontend.partials.home-block', ['b' => $entry['block']])
    @endif

@elseif($entry['name'] === 'ad_middle')

    @include('frontend.partials.ad', ['slot' => 'home_middle', 'adObj' => $entry['ad']])

@else

    <div class="sa-sec{{ $entry['full'] ? ' sa-full' : '' }}{{ $bgClass }}{{ ($entry['show_title'] ?? true) ? '' : ' sa-nohead' }}" style="--cat:{{ $entry['color'] ?? '#123b68' }};@if($bg) background: {{ $bg }};@endif">

        @switch($entry['name'])

            @case('hero')
                @php
                    $heroViews = [
                        'classic' => 'frontend.sections.hero',
                        'slider'  => 'frontend.sections.hero-slider',
                        'stack'   => 'frontend.sections.hero-stack',
                    ];
                    $heroLayout = $entry['layout'] ?? 'classic';
                @endphp

                @if(isset($heroViews[$heroLayout]))
                    @include($heroViews[$heroLayout], ['limit' => $entry['limit']])
                @elseif(isset($views[$heroLayout]))
                    {{-- new block styles: the lead story first, then the latest stories (the "popular" list is the latest ten) --}}
                    @php
                        $heroItems = collect([$leadNews])
                            ->merge(($popularNews ?? collect())->where('id', '!=', $leadNews->id))
                            ->filter()
                            ->values();
                    @endphp
                    @include($views[$heroLayout], [
                        'category'  => new \App\Models\Category(['name' => 'প্রধান সংবাদ', 'slug' => 'lead']),
                        'items'     => $heroItems,
                        'wide'      => true,
                        'limit'     => $entry['limit'],
                        'headTitle' => 'প্রধান সংবাদ',
                        'headUrl'   => false,
                    ])
                @else
                    @include($heroViews['classic'], ['limit' => $entry['limit']])
                @endif
                @break

            @case('featured')
                @php $featuredLayout = $entry['layout'] ?? 'cards'; @endphp

                @if($featuredLayout !== 'cards' && isset($views[$featuredLayout]))
                    @include($views[$featuredLayout], [
                        'category'  => new \App\Models\Category(['name' => 'নির্বাচিত সংবাদ', 'slug' => 'featured']),
                        'items'     => $featuredNews,
                        'wide'      => $entry['full'],
                        'limit'     => $entry['limit'],
                        'headTitle' => 'নির্বাচিত সংবাদ',
                        'headUrl'   => false,
                    ])
                @else
                    @include('frontend.sections.editors-choice', ['limit' => $entry['limit']])
                @endif
                @break

            @case('videos')
                @include('frontend.partials.video-block', ['limit' => $entry['limit'], 'layout' => $entry['layout'] ?? 'lead'])
                @break

            @case('latest')
                @include('frontend.sections.latest-news', ['layout' => $entry['layout'] ?? 'rows'])
                @break

        @endswitch

    </div>

@endif
