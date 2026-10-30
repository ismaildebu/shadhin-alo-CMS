@php

    $entertainmentCategory = $categories->firstWhere('slug', 'entertainment');

    $entertainmentNews = $entertainmentCategory
        ? ($categoryNews[$entertainmentCategory->id] ?? collect())
        : collect();

@endphp

@if($entertainmentNews->count())

<section class="mb-5">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">

        <h3 class="fw-bold text-danger mb-0">
            🎬 বিনোদন
        </h3>

        <a href="#" class="text-decoration-none fw-semibold">
            আরও »
        </a>

    </div>

    <div class="row">

        {{-- Left Big News --}}
        <div class="col-lg-7">

            @php
                $lead = $entertainmentNews->first();
            @endphp

            @if($lead)

            <div class="card border-0 shadow-sm h-100">

                @if($lead->image)
<img src="{{ asset('uploads/news/'.$lead->image) }}"
                     class="card-img-top"
                     style="height:420px;object-fit:cover;"
                     alt="{{ $lead->title }}">
@else
@include('frontend.partials.no-image', ['news' => $lead, 'sizeClass' => 'card-img-top'])
@endif

                <div class="card-body">

                    <h2 class="fw-bold">

                        <a href="{{ route('frontend.news.show',$lead->slug) }}"
                           class="text-dark text-decoration-none">

                            {{ $lead->title }}

                        </a>

                    </h2>

                    <p class="text-muted">

                        {{ \Illuminate\Support\Str::limit(strip_tags($lead->description),150) }}

                    </p>

                </div>

            </div>

            @endif

        </div>

        {{-- Right News --}}
        <div class="col-lg-5">

            @foreach($entertainmentNews->skip(1)->take(2) as $item)

            <div class="d-flex mb-3 border-bottom pb-3">

                @if($item->image)
<img src="{{ asset('uploads/news/'.$item->image) }}"
                     style="width:110px;height:80px;object-fit:cover;"
                     class="rounded me-3"
                     alt="{{ $item->title }}">
@else
@include('frontend.partials.no-image', ['news' => $item, 'sizeClass' => 'rounded me-3'])
@endif

                <div>

                    <h6 class="fw-bold">

                        <a href="{{ route('frontend.news.show',$item->slug) }}"
                           class="text-dark text-decoration-none">

                            {{ $item->title }}

                        </a>

                    </h6>

                </div>

            </div>

            @endforeach

        </div>

    </div>

    {{-- Bottom News --}}
    <div class="row mt-4">

        @foreach($entertainmentNews->skip(3)->take(3) as $item)

        <div class="col-md-4 mb-3">

            <div class="card border-0 shadow-sm h-100">

                @if($item->image)
<img src="{{ asset('uploads/news/'.$item->image) }}"
                     class="card-img-top"
                     style="height:180px;object-fit:cover;"
                     alt="{{ $item->title }}">
@else
@include('frontend.partials.no-image', ['news' => $item, 'sizeClass' => 'card-img-top'])
@endif

                <div class="card-body">

                    <h6 class="fw-bold">

                        <a href="{{ route('frontend.news.show',$item->slug) }}"
                           class="text-dark text-decoration-none">

                            {{ $item->title }}

                        </a>

                    </h6>

                </div>

            </div>

        </div>

        @endforeach

    </div>

</section>

@endif