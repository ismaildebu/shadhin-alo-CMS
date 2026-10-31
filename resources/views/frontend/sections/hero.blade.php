@if($leadNews)
@php
    $img = fn ($n) => $n->image ? \App\Models\Media::thumbUrl($n->image, 800) : null;
    $side = $sideNews->take(max(0, min(4, (int) ($limit ?? 3))));
@endphp

<section class="mb-5">
    <div class="row g-4">

        <div class="{{ $side->isEmpty() ? 'col-12' : 'col-lg-8' }}">
            <a href="{{ route('frontend.news.show', $leadNews->slug) }}" class="sa-hero-lead">
                @if($img($leadNews))
<img src="{{ $img($leadNews) }}" alt="{{ $leadNews->title }}">
@else
@include('frontend.partials.no-image', ['news' => $leadNews, 'sizeClass' => ''])
@endif

                <div class="sa-hero-overlay">
                    <span class="sa-chip">{{ $leadNews->category->name ?? 'প্রধান সংবাদ' }}</span>
                    <h1 class="sa-hero-title">{{ $leadNews->title }}</h1>
                    <div class="sa-meta">
                        {{ $leadNews->reporter ?? 'স্বাধীন আলো' }}
                        &nbsp;|&nbsp;
                        {{ $leadNews->display_date->format('d M Y') }}
                    </div>
                </div>
            </a>
        </div>

        @if($side->isNotEmpty())
            <div class="col-lg-4">
                <div class="sa-hero-side">
                    @foreach($side as $news)
                        <a href="{{ route('frontend.news.show', $news->slug) }}" class="sa-side-item">
                            @if($img($news))
                                <img src="{{ $img($news) }}" alt="{{ $news->title }}" class="sa-side-thumb" loading="lazy">
                            @else
                                <div class="sa-side-thumb"></div>
                            @endif
                            <div>
                                <h2 class="sa-side-title">{{ \Illuminate\Support\Str::limit($news->title, 70) }}</h2>
                                <div class="sa-side-meta">{{ $news->display_date->format('d M Y') }}</div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

    </div>
</section>
@endif