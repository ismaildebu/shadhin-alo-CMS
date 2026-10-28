@php
    $ad = $adObj ?? \App\Support\Ads::pick($slot);
@endphp

@if($ad)
    <div class="sa-adslot sa-adslot-{{ $slot }}">
        @if($ad->type === 'code')
            {!! $ad->code !!}
        @elseif($ad->image_url)
            @if($ad->link_url)
                <a href="{{ route('frontend.ad.click', $ad->id) }}" target="_blank" rel="sponsored noopener">
                    <img src="{{ $ad->image_url }}" alt="{{ $ad->title }}" loading="lazy">
                </a>
            @else
                <img src="{{ $ad->image_url }}" alt="{{ $ad->title }}" loading="lazy">
            @endif
        @endif
        <span class="sa-adlabel">বিজ্ঞাপন</span>
    </div>
@endif