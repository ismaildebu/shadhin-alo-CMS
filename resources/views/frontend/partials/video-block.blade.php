@php
    $videoList = $videos ?? collect();
    $layout = $layout ?? 'lead';
    $count = max(2, min(8, (int) ($limit ?? 4)));
@endphp

@if($videoList->isNotEmpty())
<section class="mb-5">

    <div class="sa-block-head">
        <h2>ভিডিও গ্যালারি</h2>
        <a href="{{ route('frontend.videos') }}">সব ভিডিও »</a>
    </div>

    @if($layout === 'grid')

        <div class="row g-4">
            @foreach($videoList->take($count) as $video)
                <div class="col-6 col-md-4">
                    <a href="{{ route('frontend.video.show', $video->id) }}" class="sa-vcard d-block">
                        <span class="sa-vthumb">
                            <img src="{{ $video->thumbnail }}" alt="{{ $video->title }}" loading="lazy">
                            <span class="sa-play sa-play-sm"></span>
                        </span>
                        <span class="sa-vtitle d-block">{{ \Illuminate\Support\Str::limit($video->title, 70) }}</span>
                        <span class="sa-vmeta">{{ $video->published_at?->format('d M Y') }}</span>
                    </a>
                </div>
            @endforeach
        </div>

    @elseif($layout === 'strip')

        <div class="sa-scroll">
            @foreach($videoList->take($count) as $video)
                <a href="{{ route('frontend.video.show', $video->id) }}" class="sa-vcard sa-vstrip">
                    <span class="sa-vthumb">
                        <img src="{{ $video->thumbnail }}" alt="{{ $video->title }}" loading="lazy">
                        <span class="sa-play sa-play-sm"></span>
                    </span>
                    <span class="sa-vtitle d-block">{{ \Illuminate\Support\Str::limit($video->title, 70) }}</span>
                    <span class="sa-vmeta">{{ $video->published_at?->format('d M Y') }}</span>
                </a>
            @endforeach
        </div>

    @else

        @php
            $lead = $videoList->first();
            $rest = $videoList->skip(1)->take($count - 1);
        @endphp

        <div class="row g-4">

            <div class="{{ $rest->isEmpty() ? 'col-12' : 'col-md-7' }}">
                <a href="{{ route('frontend.video.show', $lead->id) }}" class="sa-vcard">
                    <span class="sa-vthumb">
                        <img src="{{ $lead->thumbnail }}" alt="{{ $lead->title }}" loading="lazy">
                        <span class="sa-play"></span>
                        <span class="sa-vlead-title">{{ \Illuminate\Support\Str::limit($lead->title, 110) }}</span>
                    </span>
                </a>
            </div>

            @if($rest->isNotEmpty())
                <div class="col-md-5">
                    <div class="sa-vside">
                        @foreach($rest as $video)
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
                    </div>
                </div>
            @endif

        </div>

    @endif

</section>
@endif