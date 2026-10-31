@if($leadNews)
@php
    $img = fn ($n) => $n->image ? \App\Models\Media::thumbUrl($n->image, 800) : null;
    $slides = collect([$leadNews])->concat($sideNews->take(max(0, min(6, (int) ($limit ?? 3)))));
@endphp

<section class="mb-5">
    <div id="hero-slider" class="carousel slide sa-slider sa-slider-hero" data-bs-ride="carousel" data-bs-interval="5000">

        @if($slides->count() > 1)
            <div class="carousel-indicators">
                @foreach($slides as $item)
                    <button type="button" data-bs-target="#hero-slider" data-bs-slide-to="{{ $loop->index }}"
                            @if($loop->first) class="active" aria-current="true" @endif
                            aria-label="স্লাইড {{ $loop->iteration }}"></button>
                @endforeach
            </div>
        @endif

        <div class="carousel-inner">
            @foreach($slides as $item)
                <div class="carousel-item {{ $loop->first ? 'active' : '' }}">
                    <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-slide">
                        @if($img($item))
                            <img src="{{ $img($item) }}" alt="{{ $item->title }}" @if(! $loop->first) loading="lazy" @endif>
                        @endif
                        <span class="sa-slide-body">
                            <span class="sa-chip">{{ $item->category->name ?? 'প্রধান সংবাদ' }}</span>
                            <span class="sa-slide-title" style="margin-top:8px;">{{ $item->title }}</span>
                            <span class="sa-slide-meta">{{ $item->reporter ?? 'স্বাধীন আলো' }} | {{ $item->display_date->format('d M Y') }}</span>
                        </span>
                    </a>
                </div>
            @endforeach
        </div>

        @if($slides->count() > 1)
            <button class="carousel-control-prev" type="button" data-bs-target="#hero-slider" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">আগের</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#hero-slider" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">পরের</span>
            </button>
        @endif

    </div>
</section>
@endif