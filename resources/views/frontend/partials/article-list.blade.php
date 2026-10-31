@if($items->isNotEmpty())
    <section class="sa-widget">
        <h2 class="sa-widget-head">{{ $title }}</h2>

        <div class="sa-list">
            @foreach($items as $item)
                <a
                    href="{{ route('frontend.news.show', $item->slug) }}"
                    class="sa-list-item"
                >
                    @if($item->image)
<img
                            src="{{ \App\Models\Media::thumbUrl($item->image, 400) }}"
                            alt="{{ $item->title }}"
                            class="sa-list-thumb"
                            loading="lazy"
                        >
@else
@include('frontend.partials.no-image', ['news' => $item, 'sizeClass' => 'sa-list-thumb'])
@endif

                    <span class="sa-list-copy">
                        <span class="sa-list-title">
                            {{ \Illuminate\Support\Str::limit($item->title, 90) }}
                        </span>

                        <time>
                            {{ $item->display_date->format('d M Y') }}
                        </time>
                    </span>
                </a>
            @endforeach
        </div>
    </section>
@endif
