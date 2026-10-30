@if($items->count())
@php
    $img = fn ($n) => $n->image ? asset('uploads/news/'.$n->image) : null;
    $rows = $items->take(max(2, (int) ($limit ?? 8)));
@endphp

<section class="mb-5">

    @include('frontend.partials.blocks.head')

    <div class="sa-two-col">
        @foreach($rows as $item)
            <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-list-item">
                @if($img($item))
<img src="{{ $img($item) }}" alt="{{ $item->title }}" class="sa-list-thumb" loading="lazy">
@else
@include('frontend.partials.no-image', ['news' => $item, 'sizeClass' => 'sa-list-thumb'])
@endif
                <div>
                    <h4 class="sa-list-title">{{ \Illuminate\Support\Str::limit($item->title, 80) }}</h4>
                    <small class="text-muted">{{ $item->display_date->format('d M Y') }}</small>
                </div>
            </a>
        @endforeach
    </div>

</section>
@endif