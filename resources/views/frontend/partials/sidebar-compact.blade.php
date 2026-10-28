@php
    $start = $offset ?? 5;
    $railItems = isset($latestNews) ? $latestNews->skip($start)->take(5) : collect();
@endphp

<div class="sa-sticky">

       @include('frontend.partials.ad', ['slot' => 'sidebar_rail'])

    @if($railItems->isNotEmpty())
        <div class="sa-widget">
            <div class="sa-widget-head">আরও সংবাদ</div>
            @foreach($railItems as $item)
                <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-rank">
                    <span class="sa-rank-no">{{ $start + $loop->iteration }}</span>
                    <span>
                        <span class="sa-rank-title">{{ $item->title }}</span>
                        <small>{{ $item->display_date->format('d M Y') }}</small>
                    </span>
                </a>
            @endforeach
        </div>
    @endif

</div>