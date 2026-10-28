@if($items->count())
@php
    $img = fn ($n) => $n->image ? asset('uploads/news/'.$n->image) : null;
       $total = (int) ($limit ?? 8);
    $cards = $items->take(min(4, $total));
    $texts = $items->skip(4)->take(max(0, $total - 4));
@endphp

<section class="mb-5">

    @include('frontend.partials.blocks.head')

    <div class="row g-3">
        @foreach($cards as $item)
            <div class="col-6 col-lg-3">
                <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-g4">
                    <span class="sa-g4-img">
                        @if($img($item))
                            <img src="{{ $img($item) }}" alt="{{ $item->title }}" loading="lazy">
                        @endif
                    </span>
                    <h3 class="sa-g4-title">{{ \Illuminate\Support\Str::limit($item->title, 75) }}</h3>
                    <span class="sa-vmeta">{{ $item->display_date->format('d M Y') }}</span>
                </a>
            </div>
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