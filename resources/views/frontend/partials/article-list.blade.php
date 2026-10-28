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
                            src="{{ asset('uploads/news/' . $item->image) }}"
                            alt="{{ $item->title }}"
                            class="sa-list-thumb"
                            loading="lazy"
                        >
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
