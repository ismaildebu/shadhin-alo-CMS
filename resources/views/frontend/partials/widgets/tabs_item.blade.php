<a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-tw-item">
    @if($item->image)
<img src="{{ \App\Models\Media::thumbUrl($item->image, 400) }}" alt="{{ $item->title }}" class="sa-tw-thumb" loading="lazy">
@else
@include('frontend.partials.no-image', ['news' => $item, 'sizeClass' => 'sa-tw-thumb'])
@endif
    <span>
        <span class="sa-tw-title">{{ \Illuminate\Support\Str::limit($item->title, 80) }}</span>
        @if($withDate ?? false)
            <small class="sa-tw-date">{{ $item->display_date->format('d M Y') }}</small>
        @endif
    </span>
</a>