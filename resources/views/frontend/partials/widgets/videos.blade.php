@php
    $videoList = ($videos ?? collect())->take(max(1, min(5, (int) ($limit ?? 3))));
@endphp

@if($videoList->isNotEmpty())
<div class="sa-widget">
    <div class="sa-widget-head">ভিডিও</div>
    <div class="p-3 sa-vside">
        @foreach($videoList as $video)
            <a href="{{ route('frontend.video.show', $video->id) }}" class="sa-vitem sa-vcard">
                <span class="sa-vthumb">
                    <img src="{{ $video->thumbnail }}" alt="{{ $video->title }}" loading="lazy">
                    <span class="sa-play sa-play-sm"></span>
                </span>
                <span>
                    <span class="sa-vtitle d-block">{{ \Illuminate\Support\Str::limit($video->title, 70) }}</span>
                    <span class="sa-vmeta">{{ $video->published_at?->format('d M Y') }}</span>
                </span>
            </a>
        @endforeach

        <a href="{{ route('frontend.videos') }}" class="sa-vmeta" style="font-weight:600; text-decoration:none;">সব ভিডিও »</a>
    </div>
</div>
@endif