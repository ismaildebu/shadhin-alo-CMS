@php
    $mode = $block['image'];
    $height = $block['image_height'] ?? null;
@endphp

<article class="sa-epaper-block">
    <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-epaper-link">
        @if($mode !== 'none' && $item->image)
            <img class="sa-epaper-img"
                 src="{{ \App\Models\Media::thumbUrl($item->image, 800) }}"
                 alt="{{ $item->title }}"
                 loading="lazy"
                 @if($mode === 'custom' && $height) style="height:{{ $height }}px" @endif>
        @endif

        <h2 class="sa-epaper-title">{{ $item->title }}</h2>

        <p class="sa-epaper-text">
            {{ \Illuminate\Support\Str::limit(strip_tags($item->summary ?: $item->description), 220) }}
        </p>

        <div class="sa-epaper-meta">
            @if($item->category){{ $item->category->name }} &middot; @endif{{ $item->display_date->format('d M Y') }}
        </div>
    </a>
</article>
