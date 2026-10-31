{{-- thumb_list: small thumbnail on the left, headline and date on the right. Several of these sit side by side (see home-columns). --}}
@if($items->count())
@php
    $img  = fn ($n) => $n->image ? \App\Models\Media::thumbUrl($n->image, 800) : null;
    $rows = $items->take(max(1, (int) ($limit ?? 4)));
@endphp

<section class="mb-5 sa-nb sa-nb-hd-chevron">

    @include('frontend.partials.blocks.head')

    <div class="sa-nb-tl">
        @foreach($rows as $item)
            <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-nb-tl-item">
                @if($img($item))
                    <img src="{{ $img($item) }}" alt="{{ $item->title }}" class="sa-nb-tl-img" loading="lazy">
                @else
                    @include('frontend.partials.no-image', ['news' => $item, 'sizeClass' => 'sa-nb-tl-img'])
                @endif
                <span class="sa-nb-tl-body">
                    <span class="sa-nb-tl-title">@if($item->shoulder)<span class="sa-nb-sh">{{ $item->shoulder }}</span>@endif{{ $item->title }}</span>
                    <small class="sa-nb-meta">{{ $item->display_date->format('d M Y') }}</small>
                </span>
            </a>
        @endforeach
    </div>

</section>
@endif
