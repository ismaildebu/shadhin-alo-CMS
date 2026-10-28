@if($items->count())
@php
    $img = fn ($n) => $n->image ? asset('uploads/news/'.$n->image) : null;
    $lead = $items->first();
    $rest = $items->skip(1)->take(max(1, (int) ($limit ?? 7) - 1));
    $bn = fn ($n) => strtr((string) $n, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
@endphp

<section class="mb-5">

    @include('frontend.partials.blocks.head')

    <div class="row g-4">

        <div class="col-md-5">
            <a href="{{ route('frontend.news.show', $lead->slug) }}" class="sa-card">
                @if($img($lead))
                    <img src="{{ $img($lead) }}" alt="{{ $lead->title }}" class="sa-card-img" loading="lazy">
                @endif
                <div class="sa-card-body">
                    <h3 class="sa-card-title">{{ $lead->title }}</h3>
                    <p class="sa-card-text">
                        {{ \Illuminate\Support\Str::limit(strip_tags($lead->summary ?: $lead->description), 110) }}
                    </p>
                </div>
            </a>
        </div>

        <div class="col-md-7">
            <ol class="sa-num-list">
                @foreach($rest as $item)
                    <li class="sa-num-item">
                        <span class="sa-num-no">{{ $bn($loop->iteration) }}</span>
                        <div>
                            <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-num-link">{{ \Illuminate\Support\Str::limit($item->title, 95) }}</a>
                            <div class="sa-vmeta">{{ $item->display_date->format('d M Y') }}</div>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>

    </div>

</section>
@endif