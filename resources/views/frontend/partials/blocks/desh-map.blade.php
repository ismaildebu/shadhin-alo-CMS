{{-- desh_map: lead + side cards (5 items) beside a clickable division map, with a division / district / upazila filter. --}}
@if($items->count())
@php
    $img      = fn ($n) => $n->image ? asset('uploads/news/'.$n->image) : null;
    $blurb    = fn ($n) => \Illuminate\Support\Str::limit(strip_tags((string) ($n->summary ?: $n->description)), 170);
    $rows     = $items->take(max(1, (int) ($limit ?? 5)))->values();
    $lead     = $rows->first();
    $left     = $rows->slice(1, 2)->values();
    $right    = $rows->slice(3, 2)->values();
    $solo     = $left->isEmpty() && $right->isEmpty();
    $leadImg  = $img($lead);
    $leadText = $blurb($lead);
    $divisions = \App\Models\Location::divisions()->orderBy('id')->get();
    $hasGeo    = $divisions->isNotEmpty();
@endphp

<section class="mb-5 sa-nb sa-nb-hd-chevron sa-desh">

    <div class="sa-desh-top">

        @include('frontend.partials.blocks.head')

        @if($hasGeo)
            <form class="sa-desh-filter" data-desh-filter data-children-url="{{ route('frontend.locations.children', ['location' => '__ID__']) }}">
                <select data-level="division" aria-label="বিভাগ">
                    <option value="">বিভাগ</option>
                    @foreach($divisions as $division)
                        <option value="{{ $division->id }}" data-url="{{ $division->url() }}">{{ $division->name_bn }}</option>
                    @endforeach
                </select>
                <select data-level="district" aria-label="জেলা" disabled>
                    <option value="">জেলা</option>
                </select>
                <select data-level="upazila" aria-label="উপজেলা" disabled>
                    <option value="">উপজেলা</option>
                </select>
                <button type="submit" class="sa-desh-go">অনুসন্ধান</button>
            </form>
        @endif

    </div>

    <div class="sa-desh-grid{{ $hasGeo ? ' sa-desh-has-map' : '' }}">

        <div class="sa-desh-news{{ $solo ? ' sa-desh-solo' : '' }}">

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

        @if($hasGeo)
            <aside class="sa-desh-map">
                <h3 class="sa-desh-map-title">এক ক্লিকে বিভাগের খবর</h3>
                @include('frontend.partials.blocks.nb-bd-map', ['divisions' => $divisions])
            </aside>
        @endif

    </div>

    @if($hasGeo)
        <script src="{{ asset('js/desh-map.js') }}" defer></script>
    @endif

</section>
@endif
