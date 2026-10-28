{{-- img_heads: first story with a big image, the rest as headline-only rows. Several of these sit side by side (see home-columns). --}}
@if($items->count())
@php
    $img   = fn ($n) => $n->image ? asset('uploads/news/'.$n->image) : null;
    $rows  = $items->take(max(1, (int) ($limit ?? 4)))->values();
    $first = $rows->first();
    $rest  = $rows->slice(1)->values();
    $firstImg = $img($first);
@endphp

<section class="mb-5 sa-nb sa-nb-hd-square">

    @include('frontend.partials.blocks.head')

    <a href="{{ route('frontend.news.show', $first->slug) }}" class="sa-nb-ih-lead">
        @if($firstImg)
            <img src="{{ $firstImg }}" alt="{{ $first->title }}" class="sa-nb-ih-img" loading="lazy">
        @endif
        <span class="sa-nb-ih-title">@if($first->shoulder)<span class="sa-nb-sh">{{ $first->shoulder }}</span>@endif{{ $first->title }}</span>
    </a>

    @foreach($rest as $item)
        <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-nb-ih-row">
            @if($item->shoulder)<span class="sa-nb-sh">{{ $item->shoulder }}</span>@endif{{ \Illuminate\Support\Str::limit($item->title, 100) }}
        </a>
    @endforeach

</section>
@endif
