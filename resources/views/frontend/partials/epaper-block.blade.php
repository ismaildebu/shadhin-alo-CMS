@php
    $mode = $block['image'];
    $height = $block['image_height'] ?? null;
    $isLead = isset($leadId) && (int) $block['news_id'] === (int) $leadId;

    $byline = trim((string) ($item->reporter ?: ($item->category->name ?? '')));
    $excerpt = \Illuminate\Support\Str::limit(trim(strip_tags((string) ($item->summary ?: $item->description))), 420);
@endphp

<article class="sa-epaper-block{{ $isLead ? ' is-lead' : '' }}">
    <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-epaper-link">
        @if($mode !== 'none' && $item->image)
            <img class="sa-epaper-img"
                 src="{{ \App\Models\Media::thumbUrl($item->image, 800) }}"
                 alt="{{ $item->title }}"
                 loading="lazy"
                 @if($mode === 'custom' && $height) style="height:{{ $height }}px" @endif>

            @if($item->image_caption || $item->image_credit)
                <p class="sa-epaper-cap">
                    {{ $item->image_caption }}@if($item->image_credit) <span>({{ $item->image_credit }})</span>@endif
                </p>
            @endif
        @endif

        @if($item->shoulder)
            <div class="sa-epaper-shoulder">{{ $item->shoulder }}</div>
        @endif

        <h2 class="sa-epaper-title">{{ $item->title }}</h2>

        @if($item->sub_title)
            <div class="sa-epaper-sub">{{ $item->sub_title }}</div>
        @endif

        @if($byline || $item->display_date)
            <div class="sa-epaper-line">
                @if($byline)<span class="sa-epaper-by">{{ $byline }}</span>@endif
                @if($item->display_date)<span class="sa-epaper-date">{{ $item->display_date->format('d M Y') }}</span>@endif
            </div>
        @endif

        @if($excerpt)
            <p class="sa-epaper-text">{{ $excerpt }}</p>
        @endif
    </a>
</article>
