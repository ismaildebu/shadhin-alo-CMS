@php

    $businessCategory = $categories->firstWhere('slug', 'business');

    $businessNews = $businessCategory
        ? ($categoryNews[$businessCategory->id] ?? collect())
        : collect();

@endphp

@if($businessNews->count())

<section class="mb-5">

    {{-- Section Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">

        <h3 class="fw-bold text-warning mb-0">
            অর্থ-বাণিজ্য
        </h3>

        <a href="#" class="text-decoration-none fw-semibold">
            আরও »
        </a>

    </div>

    @php
        $lead = $businessNews->first();
    @endphp

    <div class="row">

        {{-- Lead News --}}
        <div class="col-lg-6 mb-4">

            @if($lead)

            <div class="card border-0 shadow-sm h-100">

                @if($lead->image)
<img src="{{ asset('uploads/news/'.$lead->image) }}"
                     class="card-img-top"
                     style="height:280px;object-fit:cover;"
                     alt="{{ $lead->title }}">
@else
@include('frontend.partials.no-image', ['news' => $lead, 'sizeClass' => 'card-img-top'])
@endif

                <div class="card-body">

                    <h4 class="fw-bold">

                        <a href="{{ route('frontend.news.show',$lead->slug) }}"
                           class="text-dark text-decoration-none">

                            {{ $lead->title }}

                        </a>

                    </h4>

                    <p class="text-muted">

                        {{ \Illuminate\Support\Str::limit(strip_tags($lead->description),120) }}

                    </p>

                </div>

            </div>

            @endif

        </div>

        {{-- Right List --}}
        <div class="col-lg-6">

            @foreach($businessNews->skip(1)->take(4) as $item)

            <div class="d-flex border-bottom pb-3 mb-3">

                @if($item->image)
<img src="{{ asset('uploads/news/'.$item->image) }}"
                     style="width:90px;height:70px;object-fit:cover;"
                     class="rounded me-3"
                     alt="{{ $item->title }}">
@else
@include('frontend.partials.no-image', ['news' => $item, 'sizeClass' => 'rounded me-3'])
@endif

                <div>

                    <h6 class="fw-bold mb-1">

                        <a href="{{ route('frontend.news.show',$item->slug) }}"
                           class="text-dark text-decoration-none">

                            {{ $item->title }}

                        </a>

                    </h6>

                    <small class="text-muted">

                        {{ $item->display_date->format('d M Y') }}

                    </small>

                </div>

            </div>

            @endforeach

        </div>

    </div>

</section>

@endif