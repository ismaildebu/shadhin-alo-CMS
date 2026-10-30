{{-- lead_boxes: big boxed lead (image, headline, excerpt) on the left, boxed image cards stacked on the right. --}}
@if($items->count())
@php
    $img      = fn ($n) => $n->image ? asset('uploads/news/'.$n->image) : null;
    $blurb    = fn ($n) => \Illuminate\Support\Str::limit(strip_tags((string) ($n->summary ?: $n->description)), 190);
    $rows     = $items->take(max(1, (int) ($limit ?? 3)))->values();
    $lead     = $rows->first();
    $side     = $rows->slice(1)->values();
    $leadImg  = $img($lead);
    $leadText = $blurb($lead);
@endphp

<section class="mb-5 sa-nb sa-nb-hd-chevron">

    @include('frontend.partials.blocks.head')

    <div class="sa-nb-lb{{ $side->isEmpty() ? ' sa-nb-lb-solo' : '' }}">

        <a href="{{ route('frontend.news.show', $lead->slug) }}" class="sa-nb-lb-main sa-nb-lead">
            @if($leadImg)
<img src="{{ $leadImg }}" alt="{{ $lead->title }}" class="sa-nb-lead-img" loading="lazy">
@else
@include('frontend.partials.no-image', ['news' => $lead, 'sizeClass' => 'sa-nb-lead-img'])
@endif
            <span class="sa-nb-lead-title">@if($lead->shoulder)<span class="sa-nb-sh">{{ $lead->shoulder }}</span>@endif{{ $lead->title }}</span>
            @if($leadText !== '')
                <span class="sa-nb-lead-text">{{ $leadText }}</span>
            @endif
        </a>

        @if($side->isNotEmpty())
            <div class="sa-nb-lb-side">
                @foreach($side as $item)
                    <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-nb-lb-card">
                        @if($img($item))
                            <img src="{{ $img($item) }}" alt="{{ $item->title }}" class="sa-nb-lb-img" loading="lazy">
                        @else
                            @include('frontend.partials.no-image', ['news' => $item, 'sizeClass' => 'sa-nb-lb-img'])
                        @endif
                        <span class="sa-nb-lb-title">@if($item->shoulder)<span class="sa-nb-sh">{{ $item->shoulder }}</span>@endif{{ \Illuminate\Support\Str::limit($item->title, 90) }}</span>
                    </a>
                @endforeach
            </div>
        @endif

    </div>

</section>
@endif
