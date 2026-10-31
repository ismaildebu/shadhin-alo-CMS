{{-- lead_rlist: big lead (image, headline, time) on the left; on the right a list with the text first and a small image on the right. --}}
@if($items->count())
@php
    $img      = fn ($n) => $n->image ? \App\Models\Media::thumbUrl($n->image, 800) : null;
    $rows     = $items->take(max(1, (int) ($limit ?? 5)))->values();
    $lead     = $rows->first();
    $rest     = $rows->slice(1)->values();
    $leadImg  = $img($lead);
@endphp

<section class="mb-5 sa-nb sa-nb-hd-chevron">

    @include('frontend.partials.blocks.head')

    <div class="sa-nb-lr{{ $rest->isEmpty() ? ' sa-nb-lr-solo' : '' }}">

        <a href="{{ route('frontend.news.show', $lead->slug) }}" class="sa-nb-lr-lead sa-nb-lead">
            @if($leadImg)
<img src="{{ $leadImg }}" alt="{{ $lead->title }}" class="sa-nb-lead-img" loading="lazy">
@else
@include('frontend.partials.no-image', ['news' => $lead, 'sizeClass' => 'sa-nb-lead-img'])
@endif
            <span class="sa-nb-lead-title">@if($lead->shoulder)<span class="sa-nb-sh">{{ $lead->shoulder }}</span>@endif{{ $lead->title }}</span>
            <small class="sa-nb-meta">{{ \App\Support\BanglaTime::ago($lead->display_date) }}</small>
        </a>

        @if($rest->isNotEmpty())
            <div class="sa-nb-lr-list">
                @foreach($rest as $item)
                    <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-nb-lr-item">
                        <span class="sa-nb-lr-body">
                            <span class="sa-nb-lr-title">@if($item->shoulder)<span class="sa-nb-sh">{{ $item->shoulder }}</span>@endif{{ \Illuminate\Support\Str::limit($item->title, 100) }}</span>
                            <small class="sa-nb-meta">{{ \App\Support\BanglaTime::ago($item->display_date) }}</small>
                        </span>
                        @if($img($item))
                            <img src="{{ $img($item) }}" alt="{{ $item->title }}" class="sa-nb-lr-img" loading="lazy">
                        @else
                            @include('frontend.partials.no-image', ['news' => $item, 'sizeClass' => 'sa-nb-lr-img'])
                        @endif
                    </a>
                @endforeach
            </div>
        @endif

    </div>

</section>
@endif
