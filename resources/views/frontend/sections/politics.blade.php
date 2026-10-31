@php

    $politicsCategory = $categories->firstWhere('slug', 'rajniti');

    $politicsNews = $politicsCategory
        ? ($categoryNews[$politicsCategory->id] ?? collect())
        : collect();

@endphp

@if($politicsNews->count())

<section class="mb-5">

    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">

        <h3 class="fw-bold text-primary mb-0">
            রাজনীতি
        </h3>

        <a href="#" class="text-decoration-none fw-semibold">
            আরও »
        </a>

    </div>

    @php
        $lead = $politicsNews->first();
    @endphp

    @if($lead)

    <div class="card border-0 shadow-sm mb-4">

        @if($lead->image)
<img src="{{ \App\Models\Media::thumbUrl($lead->image, 800) }}"
             class="card-img-top"
             style="height:350px;object-fit:cover;"
             alt="{{ $lead->title }}">
@else
@include('frontend.partials.no-image', ['news' => $lead, 'sizeClass' => 'card-img-top'])
@endif

        <div class="card-body">

            <h3 class="fw-bold">

                <a href="{{ route('frontend.news.show',$lead->slug) }}"
                   class="text-dark text-decoration-none">

                    {{ $lead->title }}

                </a>

            </h3>

            <p class="text-muted">

                {{ \Illuminate\Support\Str::limit(strip_tags($lead->description),120) }}

            </p>

        </div>

    </div>

    @endif

    <div class="row">

        @foreach($politicsNews->skip(1)->take(4) as $item)

        <div class="col-md-6 mb-3">

            <div class="border-bottom pb-2">

                <a href="{{ route('frontend.news.show',$item->slug) }}"
                   class="fw-semibold text-dark text-decoration-none">

                    ● {{ $item->title }}

                </a>

            </div>

        </div>

        @endforeach

    </div>

</section>

@endif