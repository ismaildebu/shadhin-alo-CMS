@php

    $agricultureCategory = $categories->firstWhere('slug', 'agriculture');

    $agricultureNews = $agricultureCategory
        ? ($categoryNews[$agricultureCategory->id] ?? collect())
        : collect();

@endphp

@if($agricultureNews->count())

<section class="mb-5">

    {{-- Section Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">

        <h3 class="fw-bold text-success mb-0">
            🌾 কৃষি
        </h3>

        <a href="#" class="text-decoration-none fw-semibold">
            আরও »
        </a>

    </div>

    @php
        $lead = $agricultureNews->first();
    @endphp

    <div class="row">

        {{-- Left Lead News --}}
        <div class="col-lg-7">

            @if($lead)

            <div class="card border-0 shadow-sm h-100">

                @if($lead->image)

                <img src="{{ asset('uploads/news/'.$lead->image) }}"
                     class="card-img-top"
                     style="height:400px;object-fit:cover;"
                     alt="{{ $lead->title }}">

                @endif

                <div class="card-body">

                    <span class="badge bg-success mb-2">
                        কৃষি
                    </span>

                    <h3 class="fw-bold">

                        <a href="{{ route('frontend.news.show',$lead->slug) }}"
                           class="text-dark text-decoration-none">

                            {{ $lead->title }}

                        </a>

                    </h3>

                    <p class="text-muted">

                        {{ \Illuminate\Support\Str::limit(strip_tags($lead->description),150) }}

                    </p>

                </div>

            </div>

            @endif

        </div>

        {{-- Right News --}}
        <div class="col-lg-5">

            @foreach($agricultureNews->skip(1)->take(4) as $item)

            <div class="d-flex mb-3 pb-3 border-bottom">

                @if($item->image)

                <img src="{{ asset('uploads/news/'.$item->image) }}"
                     style="width:110px;height:80px;object-fit:cover;"
                     class="rounded me-3"
                     alt="{{ $item->title }}">

                @endif

                <div>

                    <h6 class="fw-bold">

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