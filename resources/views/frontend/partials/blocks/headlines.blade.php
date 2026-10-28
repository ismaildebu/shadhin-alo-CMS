@if($items->count())
@php
    $rows = $items->take(max(2, (int) ($limit ?? 10)));
@endphp

<section class="mb-5">

    @include('frontend.partials.blocks.head')

    <div class="sa-two-col">
        @foreach($rows as $item)
            <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-text-row">
                {{ \Illuminate\Support\Str::limit($item->title, 95) }}
                <small>{{ $item->display_date->format('d M Y') }}</small>
            </a>
        @endforeach
    </div>

</section>
@endif