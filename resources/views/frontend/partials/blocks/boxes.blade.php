@if($items->count())
@php
    $img = fn ($n) => $n->image ? asset('uploads/news/'.$n->image) : null;
    $wide = $wide ?? false;
    $boxes = $items->take(max(2, (int) ($limit ?? 6)));
@endphp

<section class="mb-5">

    @include('frontend.partials.blocks.head')

    <div class="row g-3">
        @foreach($boxes as $item)
            <div class="{{ $wide ? 'col-6 col-md-4 col-lg-3' : 'col-6 col-md-4' }}">
                <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-box">
                    <span class="sa-box-img">
                        @if($img($item))
                            <img src="{{ $img($item) }}" alt="{{ $item->title }}" loading="lazy">
                        @endif
                    </span>
                    <span class="sa-box-body">
                        <span class="sa-box-title">{{ \Illuminate\Support\Str::limit($item->title, 80) }}</span>
                        <span class="sa-vmeta">{{ $item->display_date->format('d M Y') }}</span>
                    </span>
                </a>
            </div>
        @endforeach
    </div>

</section>
@endif