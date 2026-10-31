@if($items->count())
@php
    $img = fn ($n) => $n->image ? \App\Models\Media::thumbUrl($n->image, 800) : null;
        $total = (int) ($limit ?? 7);
    $tiles = $items->take(min(3, $total));
    $texts = $items->skip(3)->take(max(0, $total - 3));
@endphp

<section class="mb-5">

    @include('frontend.partials.blocks.head')

    <div class="sa-trio">
        @foreach($tiles as $item)
            <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-ttile">
                @if($img($item))
<img src="{{ $img($item) }}" alt="{{ $item->title }}" loading="lazy">
@else
@include('frontend.partials.no-image', ['news' => $item, 'sizeClass' => ''])
@endif
                <span class="sa-ttile-body">
                    <span class="sa-ttile-title">{{ \Illuminate\Support\Str::limit($item->title, 80) }}</span>
                    <span class="sa-ttile-meta">{{ $item->display_date->format('d M Y') }}</span>
                </span>
            </a>
        @endforeach
    </div>

    @if($texts->isNotEmpty())
        <ul class="sa-bullets">
            @foreach($texts as $item)
                <li>
                    <a href="{{ route('frontend.news.show', $item->slug) }}">{{ \Illuminate\Support\Str::limit($item->title, 90) }}</a>
                </li>
            @endforeach
        </ul>
    @endif

</section>
@endif