{{-- Small card: image, title (with shoulder tag), date. Needs: $item --}}
@php $cardImg = $item->image ? asset('uploads/news/'.$item->image) : null; @endphp
<a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-nb-card">
    @if($cardImg)
        <img src="{{ $cardImg }}" alt="{{ $item->title }}" class="sa-nb-card-img" loading="lazy">
    @else
        @include('frontend.partials.no-image', ['news' => $item, 'sizeClass' => 'sa-nb-card-img'])
    @endif
    <span class="sa-nb-card-title">@if($item->shoulder)<span class="sa-nb-sh">{{ $item->shoulder }}</span>@endif{{ \Illuminate\Support\Str::limit($item->title, 90) }}</span>
    <small class="sa-nb-meta">{{ $item->display_date->format('d M Y') }}</small>
</a>
