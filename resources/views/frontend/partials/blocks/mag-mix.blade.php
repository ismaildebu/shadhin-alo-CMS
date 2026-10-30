{{-- mag_mix: centre lead, 2 cards on the left, 3 on the right, and a row of 3 side-by-side items below (9 items). --}}
@if($items->count())
@php
    $img      = fn ($n) => $n->image ? asset('uploads/news/'.$n->image) : null;
    $blurb    = fn ($n) => \Illuminate\Support\Str::limit(strip_tags((string) ($n->summary ?: $n->description)), 170);
    $rows     = $items->take(max(1, (int) ($limit ?? 9)))->values();
    $lead     = $rows->first();
    $left     = $rows->slice(1, 2)->values();
    $right    = $rows->slice(3, 3)->values();
    $bottom   = $rows->slice(6, 3)->values();
    $leadImg  = $img($lead);
    $leadText = $blurb($lead);
@endphp

<section class="mb-5 sa-nb sa-nb-hd-plain">

    @include('frontend.partials.blocks.head')

    <div class="sa-nb-mm">

        @if($left->isNotEmpty())
            <div class="sa-nb-mm-left">
                @foreach($left as $item)
                    @include('frontend.partials.blocks.nb-card', ['item' => $item])
                @endforeach
            </div>
        @endif

        <div class="sa-nb-mm-lead">
            <a href="{{ route('frontend.news.show', $lead->slug) }}" class="sa-nb-lead">
                @if($leadImg)
<img src="{{ $leadImg }}" alt="{{ $lead->title }}" class="sa-nb-lead-img" loading="lazy">
@else
@include('frontend.partials.no-image', ['news' => $lead, 'sizeClass' => 'sa-nb-lead-img'])
@endif
                <span class="sa-nb-lead-title">@if($lead->shoulder)<span class="sa-nb-sh">{{ $lead->shoulder }}</span>@endif{{ $lead->title }}</span>
                @if($leadText !== '')
                    <span class="sa-nb-lead-text">{{ $leadText }}</span>
                @endif
                <small class="sa-nb-meta">{{ $lead->display_date->format('d M Y') }}</small>
            </a>
        </div>

        @if($right->isNotEmpty())
            <div class="sa-nb-mm-right">
                @foreach($right as $item)
                    @include('frontend.partials.blocks.nb-card', ['item' => $item])
                @endforeach
            </div>
        @endif

    </div>

    @if($bottom->isNotEmpty())
        <div class="sa-nb-mm-bottom">
            @foreach($bottom as $item)
                <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-nb-bt-item">
                    @if($img($item))
                        <img src="{{ $img($item) }}" alt="{{ $item->title }}" class="sa-nb-bt-img" loading="lazy">
                    @else
                        @include('frontend.partials.no-image', ['news' => $item, 'sizeClass' => 'sa-nb-bt-img'])
                    @endif
                    <span class="sa-nb-bt-body">
                        <span class="sa-nb-bt-title">@if($item->shoulder)<span class="sa-nb-sh">{{ $item->shoulder }}</span>@endif{{ \Illuminate\Support\Str::limit($item->title, 90) }}</span>
                        <small class="sa-nb-meta">{{ $item->display_date->format('d M Y') }}</small>
                    </span>
                </a>
            @endforeach
        </div>
    @endif

</section>
@endif
