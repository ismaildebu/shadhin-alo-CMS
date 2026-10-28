<a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-tw-item">
    @if($item->image)
        <img src="{{ asset('uploads/news/'.$item->image) }}" alt="{{ $item->title }}" class="sa-tw-thumb" loading="lazy">
    @endif
    <span>
        <span class="sa-tw-title">{{ \Illuminate\Support\Str::limit($item->title, 80) }}</span>
        @if($withDate ?? false)
            <small class="sa-tw-date">{{ $item->display_date->format('d M Y') }}</small>
        @endif
    </span>
</a>