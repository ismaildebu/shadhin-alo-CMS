@php
    /*
     * Expects: $news, $design
     * Optional: $titleTag ('h1' for the main article, 'h2' for auto-loaded ones),
     *           $withAds (auto-loaded articles skip ads: scripts in injected HTML do not run)
     */
    $titleTag = $titleTag ?? 'h1';
    $withAds = $withAds ?? true;
    $show = $design['toggles'];
    $articleBody = \App\Support\ArticleBody::render($news->description);

    // লেখকের নাম, ছবি ও পরিচিতি (অটো-লোড হওয়া খবরেও কাজ করে, তাই এখানেই হিসাব)
    $by = \App\Support\Avatars::byline($news);
    $bylineName = $by['name'] !== '' ? $by['name'] : 'স্বাধীন আলো';
    $bylineMark = $by['name'] !== '' ? \Illuminate\Support\Str::substr($by['name'], 0, 1) : 'সা';
@endphp

<div
    class="sa-autoload-item @if($titleTag !== 'h1') is-loaded @endif"
    data-url="{{ route('frontend.news.show', $news->slug) }}"
    data-title="{{ ($news->seo_title ?: $news->title) . ' | ' . \App\Support\Settings::get('site_name') }}"
>
    <article class="sa-article">
        @if($news->shoulder)
            <div class="sa-article-shoulder">
                {{ $news->shoulder }}
            </div>
        @endif

        <{{ $titleTag }} class="sa-article-title">
            {{ $news->title }}
        </{{ $titleTag }}>

        @if($news->sub_title)
            <p class="sa-article-sub">
                {{ $news->sub_title }}
            </p>
        @endif

        @if($show['summary'] && $news->summary)
            <p class="sa-article-summary">
                {{ $news->summary }}
            </p>
        @endif

        <div class="sa-article-meta">
            <div class="sa-byline">
                @if($by['photo'])
                    <img
                        src="{{ $by['photo'] }}"
                        alt="{{ $by['name'] }}"
                        class="sa-author-mark"
                        loading="lazy"
                    >
                @else
                    <span class="sa-author-mark" aria-hidden="true">{{ $bylineMark }}</span>
                @endif

                <span>
                    <strong>
                        {{ $bylineName }}
                    </strong>

                    <time datetime="{{ $news->display_date->toIso8601String() }}">
                        প্রকাশ: {{ $news->display_date->format('d M Y, h:i A') }}
                    </time>
                </span>
            </div>

            @if($show['share'])
                @php $articleUrl = route('frontend.news.show', $news->slug); @endphp
                <div class="sa-share" aria-label="সংবাদ শেয়ার করুন">
                    <a
                        href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($articleUrl) }}"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Facebook
                    </a>

                    <a
                        href="https://wa.me/?text={{ urlencode($news->title . ' ' . $articleUrl) }}"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        WhatsApp
                    </a>

                    @if($show['photocard'] && $news->card_url)
                        <a href="{{ $news->card_url }}" download>
                            ফটোকার্ড
                        </a>
                    @endif
                </div>
            @endif
        </div>

        @if($news->image)
            <figure class="sa-figure">
                <img
                    src="{{ asset('uploads/news/' . $news->image) }}"
                    alt="{{ $news->image_caption ?: $news->title }}"
                    @if($titleTag === 'h1') fetchpriority="high" @else loading="lazy" @endif
                >

                @if($news->image_caption || $news->image_credit)
                    <figcaption>
                        {{ $news->image_caption }}

                        @if($news->image_credit)
                            <span>(ছবি: {{ $news->image_credit }})</span>
                        @endif
                    </figcaption>
                @endif
            </figure>
        @elseif($show['no_image_card'])
            {{-- No photo: logo, category, location text and the highlighted division map. --}}
            <figure class="sa-figure">
                @include('frontend.partials.no-image', ['news' => $news, 'variant' => 'hero'])
            </figure>
        @endif

        @if($withAds)
            @include('frontend.partials.ad', ['slot' => 'article_top'])
        @endif

        <div class="sa-article-body">
            {!! $articleBody !!}
        </div>

        @if($withAds)
            @include('frontend.partials.ad', ['slot' => 'article_bottom'])
        @endif
    </article>

    @if($show['location'] && $news->location)
        <div class="sa-article-location">
            <strong>স্থান:</strong>

            @foreach($news->location->path() as $place)
                <a href="{{ $place->url() }}">{{ $place->name_bn }}</a>@if(!$loop->last)<span>, </span>@endif
            @endforeach
        </div>
    @endif

    @if($show['tags'] && $news->tags->isNotEmpty())
        <div class="sa-article-tags">
            <strong>ট্যাগ:</strong>

            @foreach($news->tags as $tag)
                <a
                    href="{{ route('frontend.tag.show', $tag->slug) }}"
                    class="sa-tag"
                >
                    {{ $tag->name }}
                </a>
            @endforeach
        </div>
    @endif

    {{-- লেখক বক্স: ছবি, নাম ও পরিচিতি। ড্যাশবোর্ডের ডিজাইনে author_box সুইচ না থাকলে সবসময় দেখায়। --}}
    @if($show['author_box'] ?? true)
        @include('frontend.partials.author-box', ['by' => $by])
    @endif
</div>