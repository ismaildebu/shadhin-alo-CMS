@if($items->count())
@php
    $lead = $items->first();
    $rest = $items->skip(1)->take(max(1, (int) ($limit ?? 5) - 1));
    $img  = fn ($n) => $n->image ? \App\Models\Media::thumbUrl($n->image, 800) : null;
@endphp

<section class="mb-5">

       @include('frontend.partials.blocks.head')

    <div class="row g-4">

        <div class="col-md-6">
            <a href="{{ route('frontend.news.show', $lead->slug) }}" class="sa-card">
                @if($img($lead))
<img src="{{ $img($lead) }}" alt="{{ $lead->title }}" class="sa-card-img" loading="lazy">
@else
@include('frontend.partials.no-image', ['news' => $lead, 'sizeClass' => 'sa-card-img'])
@endif
                <div class="sa-card-body">
                    <h3 class="sa-card-title">{{ $lead->title }}</h3>
                    <p class="sa-card-text">
                        {{ \Illuminate\Support\Str::limit(strip_tags($lead->summary ?: $lead->description), 100) }}
                    </p>
                </div>
            </a>
        </div>

        <div class="col-md-6">
            <div class="sa-list">
                @foreach($rest as $item)
                    <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-list-item">
                        @if($img($item))
<img src="{{ $img($item) }}" alt="{{ $item->title }}" class="sa-list-thumb" loading="lazy">
@else
@include('frontend.partials.no-image', ['news' => $item, 'sizeClass' => 'sa-list-thumb'])
@endif
                        <div>
                            <h4 class="sa-list-title">{{ \Illuminate\Support\Str::limit($item->title, 80) }}</h4>
                            <small class="text-muted">{{ $item->display_date->format('d M Y') }}</small>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

    </div>

</section>
@endif