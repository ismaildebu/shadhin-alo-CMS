@if($items->count())
@php
    $wide = $wide ?? false;
@endphp

<section class="mb-5">

    @include('frontend.partials.blocks.head')

    <div class="sa-scroll">
        @foreach($items->take($limit ?? ($wide ? 8 : 5)) as $item)
            <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-card sa-scroll-card">
                @if($item->image)
                    <img src="{{ asset('uploads/news/'.$item->image) }}"
                         alt="{{ $item->title }}" class="sa-scroll-img" loading="lazy">
                @endif
                <div class="sa-card-body">
                    <h3 class="sa-card-title">{{ \Illuminate\Support\Str::limit($item->title, 85) }}</h3>
                    <small class="text-muted">{{ $item->display_date->format('d M Y') }}</small>
                </div>
            </a>
        @endforeach
    </div>

</section>
@endif