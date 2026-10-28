@if($items->count())
@php
    $img = fn ($n) => $n->image ? asset('uploads/news/'.$n->image) : null;
    $lead = $items->first();
    $rest = $items->skip(1)->take(max(1, (int) ($limit ?? 7) - 1));
@endphp

<section class="mb-5">

    @include('frontend.partials.blocks.head')

    <a href="{{ route('frontend.news.show', $lead->slug) }}" class="sa-wide-lead">
        @if($img($lead))
            <span class="sa-wide-img">
                <img src="{{ $img($lead) }}" alt="{{ $lead->title }}" loading="lazy">
            </span>
        @endif
        <span class="sa-wide-body">
            <h3 class="sa-wide-title">{{ $lead->title }}</h3>
            <p class="sa-card-text">{{ \Illuminate\Support\Str::limit(strip_tags($lead->summary ?: $lead->description), 160) }}</p>
            <span class="sa-vmeta">{{ $lead->reporter ?: 'স্বাধীন আলো' }} &middot; {{ $lead->display_date->format('d M Y') }}</span>
        </span>
    </a>

    @if($rest->isNotEmpty())
        <div class="sa-two-col mt-3">
            @foreach($rest as $item)
                <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-text-row">
                    {{ \Illuminate\Support\Str::limit($item->title, 90) }}
                    <small>{{ $item->display_date->format('d M Y') }}</small>
                </a>
            @endforeach
        </div>
    @endif

</section>
@endif