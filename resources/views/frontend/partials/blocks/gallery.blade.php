@if($items->count())
@php
    $img = fn ($n) => $n->image ? asset('uploads/news/'.$n->image) : null;
    $tiles = $items->take(max(2, (int) ($limit ?? 8)));
@endphp

<section class="mb-5">

    @include('frontend.partials.blocks.head')

    <div class="sa-gal">
        @foreach($tiles as $item)
            <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-gal-item">
                @if($img($item))
                    <img src="{{ $img($item) }}" alt="{{ $item->title }}" loading="lazy">
                @endif
                <span class="sa-gal-cap">{{ \Illuminate\Support\Str::limit($item->title, 55) }}</span>
            </a>
        @endforeach
    </div>

</section>
@endif