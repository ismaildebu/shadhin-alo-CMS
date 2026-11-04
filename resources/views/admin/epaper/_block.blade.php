@php
    $mode = $block['image'] ?? 'auto';
    $height = $block['image_height'] ?? null;
@endphp

<article class="ep-block" data-news="{{ $block['news_id'] }}" data-image="{{ $mode }}" data-height="{{ $height }}" data-hasimg="{{ $item->image ? 1 : 0 }}">
    <span class="ep-block-grip" aria-hidden="true"></span>

    <div class="ep-block-imgwrap">
        @if($item->image)
            <img class="ep-block-img" src="{{ \App\Models\Media::thumbUrl($item->image, 400) }}" alt="">
        @else
            <img class="ep-block-img" alt="">
        @endif
        <span class="ep-img-grip" title="টেনে ছবির সাইজ বদলান"></span>
    </div>

    <h4 class="ep-block-title">{{ $item->title }}</h4>

    <div class="ep-block-meta">
        <span class="ep-block-cat">{{ $item->category->name ?? '' }}</span>
        <span class="ep-block-date">{{ $item->display_date?->format('d M Y') }}</span>
    </div>

    <div class="ep-block-tools">
        <button type="button" class="ep-tool" data-act="img-none">ছবি বাদ</button>
        <button type="button" class="ep-tool" data-act="img-auto">ছবি স্বাভাবিক</button>
        <button type="button" class="ep-tool" data-act="img-custom">ছবি টেনে</button>
        <span class="ep-block-nav">
            <button type="button" class="ep-tool" data-act="up" title="উপরে নিন">↑</button>
            <button type="button" class="ep-tool" data-act="down" title="নিচে নিন">↓</button>
            <button type="button" class="ep-tool ep-tool-del" data-act="remove" title="পাতা থেকে সরান">✕</button>
        </span>
    </div>
</article>
