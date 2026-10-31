@if($items->count())
<section class="mb-5">

    @include('frontend.partials.blocks.head')

    <div class="sa-mosaic">
       @foreach($items->take(min(5, max(2, (int) ($limit ?? 5)))) as $item)
            <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-tile">
                @if($item->image)
<img src="{{ \App\Models\Media::thumbUrl($item->image, 800) }}" alt="{{ $item->title }}" loading="lazy">
@else
@include('frontend.partials.no-image', ['news' => $item, 'sizeClass' => ''])
@endif
                <div class="sa-tile-body">
                    <h3 class="sa-tile-title">{{ \Illuminate\Support\Str::limit($item->title, $loop->first ? 110 : 70) }}</h3>
                    <div class="sa-tile-meta">{{ $item->display_date->format('d M Y') }}</div>
                </div>
            </a>
        @endforeach
    </div>

</section>
@endif