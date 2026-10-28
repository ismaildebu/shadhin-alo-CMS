@extends('frontend.layouts.app')

@section('title', $video->title.' | স্বাধীন আলো')

@section('meta')
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($video->description ?: $video->title), 160) }}">
    <meta property="og:title" content="{{ $video->title }}">
    <meta property="og:image" content="{{ $video->thumbnail }}">
@endsection

@section('content')

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">হোম</a></li>
        <li class="breadcrumb-item"><a href="{{ route('frontend.videos') }}">ভিডিও গ্যালারি</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ \Illuminate\Support\Str::limit($video->title, 50) }}</li>
    </ol>
</nav>

<div class="row g-4">

    <div class="col-lg-8">

        <div class="sa-embed">
            <iframe src="{{ $video->embed_url }}"
                    title="{{ $video->title }}"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    referrerpolicy="strict-origin-when-cross-origin"
                    allowfullscreen
                    loading="lazy"></iframe>
        </div>

        <h1 class="sa-article-title mt-4">{{ $video->title }}</h1>

        <div class="sa-article-meta">
            <time datetime="{{ $video->published_at?->toIso8601String() }}">
                {{ $video->published_at?->format('d M Y, h:i A') }}
            </time>
            <div class="sa-share">
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(url()->current()) }}"
                   target="_blank" rel="noopener">Facebook</a>
                <a href="https://wa.me/?text={{ urlencode($video->title.' '.url()->current()) }}"
                   target="_blank" rel="noopener">WhatsApp</a>
            </div>
        </div>

        @if($video->description)
            <div class="sa-video-desc">{{ $video->description }}</div>
        @endif

    </div>

    <div class="col-lg-4">
        @if($related->isNotEmpty())
            <div class="sa-widget">
                <div class="sa-widget-head">আরও ভিডিও</div>
                <div class="p-3 sa-vside">
                    @foreach($related as $item)
                        <a href="{{ route('frontend.video.show', $item->id) }}" class="sa-vitem sa-vcard">
                            <span class="sa-vthumb">
                                <img src="{{ $item->thumbnail }}" alt="{{ $item->title }}" loading="lazy">
                                <span class="sa-play sa-play-sm"></span>
                            </span>
                            <span>
                                <span class="sa-vtitle d-block">{{ \Illuminate\Support\Str::limit($item->title, 70) }}</span>
                                <span class="sa-vmeta">{{ $item->published_at?->format('d M Y') }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

</div>

@endsection