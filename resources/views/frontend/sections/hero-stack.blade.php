@if($leadNews)
@php
    $img = fn ($n) => $n->image ? \App\Models\Media::thumbUrl($n->image, 800) : null;
    $cards = $sideNews->take(max(0, min(6, (int) ($limit ?? 3))));
@endphp

<section class="mb-5">

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

    @if($cards->isNotEmpty())
        <div class="row g-3 mt-1">
            @foreach($cards as $news)
                <div class="col-6 col-lg-3">
                    <a href="{{ route('frontend.news.show', $news->slug) }}" class="sa-g4">
                        <span class="sa-g4-img">
                            @if($img($news))
<img src="{{ $img($news) }}" alt="{{ $news->title }}" loading="lazy">
@else
@include('frontend.partials.no-image', ['news' => $news, 'sizeClass' => ''])
@endif
                        </span>
                        <h3 class="sa-g4-title">{{ \Illuminate\Support\Str::limit($news->title, 75) }}</h3>
                        <span class="sa-vmeta">{{ $news->display_date->format('d M Y') }}</span>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

</section>
@endif