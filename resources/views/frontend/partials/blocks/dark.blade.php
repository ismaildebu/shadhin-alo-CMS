@if($items->count())
@php
    $img = fn ($n) => $n->image ? \App\Models\Media::thumbUrl($n->image, 800) : null;
    $wide = $wide ?? false;
    $lead = $items->first();
   $rest = $items->skip(1)->take(max(1, (int) ($limit ?? 5) - 1));
@endphp

<section class="mb-5">
    <div class="sa-dark">

        <div class="sa-dark-head">
            <h2>{{ $headTitle ?? $category->name }}</h2>
            @if(($headUrl ?? null) !== false)
                <a href="{{ $headUrl ?? route('frontend.category.show', $category->slug) }}">আরও »</a>
            @endif
        </div>

        <div class="row g-4">

            <div class="{{ $wide ? 'col-lg-6' : 'col-md-7' }}">
                <a href="{{ route('frontend.news.show', $lead->slug) }}" class="sa-dlead">
                    @if($img($lead))
<img src="{{ $img($lead) }}" alt="{{ $lead->title }}" loading="lazy">
@else
@include('frontend.partials.no-image', ['news' => $lead, 'sizeClass' => ''])
@endif
                    <span class="sa-dlead-body">
                        <span class="sa-dlead-title">{{ \Illuminate\Support\Str::limit($lead->title, 110) }}</span>
                        <span class="sa-dlead-meta">{{ $lead->display_date->format('d M Y') }}</span>
                    </span>
                </a>
            </div>

            <div class="{{ $wide ? 'col-lg-6' : 'col-md-5' }}">
                @if($wide)
                    <div class="row g-3">
                        @foreach($rest as $item)
                            <div class="col-6">
                                <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-dcard">
                                    @if($img($item))
<img src="{{ $img($item) }}" alt="{{ $item->title }}" loading="lazy">
@else
@include('frontend.partials.no-image', ['news' => $item, 'sizeClass' => ''])
@endif
                                    <span class="sa-dcard-title">{{ \Illuminate\Support\Str::limit($item->title, 70) }}</span>
                                    <span class="sa-dcard-meta">{{ $item->display_date->format('d M Y') }}</span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="sa-dside">
                        @foreach($rest as $item)
                            <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-ditem">
                                @if($img($item))
<img src="{{ $img($item) }}" alt="{{ $item->title }}" class="sa-ditem-img" loading="lazy">
@else
@include('frontend.partials.no-image', ['news' => $item, 'sizeClass' => 'sa-ditem-img'])
@endif
                                <span>
                                    <span class="sa-ditem-title">{{ \Illuminate\Support\Str::limit($item->title, 70) }}</span>
                                    <span class="sa-ditem-meta">{{ $item->display_date->format('d M Y') }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>

    </div>
</section>
@endif