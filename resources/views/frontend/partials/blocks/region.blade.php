@if($items->count())
@php
    $img = fn ($n) => $n->image ? asset('uploads/news/'.$n->image) : null;
    $total = (int) ($limit ?? 9);
    $cards = $items->take(min(3, $total));
    $rest = $items->skip(3)->take(max(0, $total - 3));
@endphp

<section class="mb-5">

    @include('frontend.partials.blocks.head')

    <div class="row g-3 mb-4">
        @foreach($cards as $item)
            <div class="col-md-4">
                <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-card">
                    @if($img($item))
                        <img src="{{ $img($item) }}" alt="{{ $item->title }}" class="sa-feat-img" loading="lazy">
                    @endif
                    <div class="sa-card-body">
                        <h3 class="sa-feat-title" style="margin-top:0;">{{ \Illuminate\Support\Str::limit($item->title, 80) }}</h3>
                        <small class="text-muted">{{ $item->display_date->format('d M Y') }}</small>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    @if($rest->isNotEmpty())
        <div class="sa-two-col">
            @foreach($rest as $item)
                <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-list-item">
                    @if($img($item))
                        <img src="{{ $img($item) }}" alt="{{ $item->title }}" class="sa-list-thumb" loading="lazy">
                    @endif
                    <div>
                        <h4 class="sa-list-title">{{ \Illuminate\Support\Str::limit($item->title, 75) }}</h4>
                        <small class="text-muted">{{ $item->display_date->format('d M Y') }}</small>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

</section>
@endif