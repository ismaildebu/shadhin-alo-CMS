{{-- lead_sides: big lead in the middle, two cards on each side (5 items). --}}
@if($items->count())
@php
    $img      = fn ($n) => $n->image ? \App\Models\Media::thumbUrl($n->image, 800) : null;
    $blurb    = fn ($n) => \Illuminate\Support\Str::limit(strip_tags((string) ($n->summary ?: $n->description)), 170);
    $rows     = $items->take(max(1, (int) ($limit ?? 5)))->values();
    $lead     = $rows->first();
    $left     = $rows->slice(1, 2)->values();
    $right    = $rows->slice(3, 2)->values();
    $solo     = $left->isEmpty() && $right->isEmpty();
    $isWide   = (bool) ($wide ?? true);
    $leadImg  = $img($lead);
    $leadText = $blurb($lead);
@endphp

<section class="mb-5 sa-nb sa-nb-hd-chevron">

    @include('frontend.partials.blocks.head')

    <div class="sa-nb-ls {{ $isWide ? 'sa-nb-ls-wide' : 'sa-nb-ls-narrow' }}{{ $solo ? ' sa-nb-ls-solo' : '' }}">

        @if($left->isNotEmpty())
            <div class="sa-nb-ls-left">
                @foreach($left as $item)
                    @include('frontend.partials.blocks.nb-card', ['item' => $item])
                @endforeach
            </div>
        @endif

        <div class="sa-nb-ls-lead">
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
            <div class="sa-nb-ls-right">
                @foreach($right as $item)
                    @include('frontend.partials.blocks.nb-card', ['item' => $item])
                @endforeach
            </div>
        @endif

    </div>

</section>
@endif
