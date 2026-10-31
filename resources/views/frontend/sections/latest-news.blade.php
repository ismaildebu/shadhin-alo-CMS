@php
    $layout = $layout ?? 'rows';
@endphp

<section class="mb-5">

    <div class="sa-block-head">
        <h2>সর্বশেষ সংবাদ</h2>
        <span class="text-muted small">মোট {{ $latestNews->total() }}টি সংবাদ</span>
    </div>

    @if($latestNews->isEmpty())

        <p class="text-muted py-4 text-center">এই মুহূর্তে দেখানোর মতো কোনো সংবাদ নেই।</p>

    @elseif($layout === 'cards')

        <div class="row g-3">
            @foreach($latestNews as $item)
                <div class="col-sm-6">
                    <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-card">
                        @if($item->image)
<img src="{{ \App\Models\Media::thumbUrl($item->image, 800) }}"
                                 alt="{{ $item->title }}" class="sa-feat-img" loading="lazy">
@else
@include('frontend.partials.no-image', ['news' => $item, 'sizeClass' => 'sa-feat-img'])
@endif
                        <div class="sa-card-body">
                            @if($item->category)
                                <div class="sa-row-cat">{{ $item->category->name }}</div>
                            @endif
                            <h3 class="sa-feat-title" style="margin-top:2px;">{{ \Illuminate\Support\Str::limit($item->title, 85) }}</h3>
                            <small class="text-muted">{{ $item->display_date->format('d M Y') }}</small>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

    @elseif($layout === 'compact')

        <div>
            @foreach($latestNews as $item)
                <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-text-row">
                    {{ $item->title }}
                    <small>
                        @if($item->category) {{ $item->category->name }} &middot; @endif
                        {{ $item->display_date->format('d M Y') }}
                    </small>
                </a>
            @endforeach
        </div>

    @else

        <div class="d-flex flex-column gap-3">
            @foreach($latestNews as $item)
                <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-row-card">
                    @if($item->image)
<img src="{{ \App\Models\Media::thumbUrl($item->image, 800) }}"
                             alt="{{ $item->title }}" class="sa-row-img" loading="lazy">
@else
@include('frontend.partials.no-image', ['news' => $item, 'sizeClass' => 'sa-row-img'])
@endif
                    <div class="sa-row-body">
                        @if($item->category)
                            <div class="sa-row-cat">{{ $item->category->name }}</div>
                        @endif
                        <h3 class="sa-row-title">{{ $item->title }}</h3>
                        <p class="sa-card-text mb-2 d-none d-md-block">
                            {{ \Illuminate\Support\Str::limit(strip_tags($item->summary ?: $item->description), 140) }}
                        </p>
                        <time class="small text-muted" datetime="{{ $item->display_date->toIso8601String() }}">
                            {{ $item->display_date->format('d M Y') }}
                        </time>
                    </div>
                </a>
            @endforeach
        </div>

    @endif

    @if($latestNews->hasPages())
        <div class="d-flex justify-content-center mt-4">
            {{ $latestNews->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    @endif

</section>