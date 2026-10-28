@if($items->count())
<section class="mb-5">

    @include('frontend.partials.blocks.head')

    <div class="sa-op-grid">
        @foreach($items->take($limit ?? 4) as $item)
            @php $author = $item->reporter ?: 'স্বাধীন আলো'; @endphp
            <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-op-card">
                <h3 class="sa-op-title">{{ \Illuminate\Support\Str::limit($item->title, 95) }}</h3>
                <div class="sa-op-author">
                    <span class="sa-op-avatar">{{ \Illuminate\Support\Str::substr($author, 0, 1) }}</span>
                    <span>
                        {{ $author }}<br>
                        <small>{{ $item->display_date->format('d M Y') }}</small>
                    </span>
                </div>
            </a>
        @endforeach
    </div>

</section>
@endif