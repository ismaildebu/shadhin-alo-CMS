@php

    $technologyCategory = $categories->firstWhere('slug', 'technology');

    $technologyNews = $technologyCategory
        ? ($categoryNews[$technologyCategory->id] ?? collect())
        : collect();

@endphp

@if($technologyNews->count())

<section class="mb-5">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">

        <h3 class="fw-bold text-info mb-0">
            💻 বিজ্ঞান ও প্রযুক্তি
        </h3>

        <a href="#" class="text-decoration-none fw-semibold">
            আরও »
        </a>

    </div>

    <div class="row">

        @foreach($technologyNews->take(4) as $item)

        <div class="col-lg-3 col-md-6 mb-4">

            <div class="card border-0 shadow-sm h-100 rounded-4 overflow-hidden">

                @if($item->image)

                <img src="{{ asset('uploads/news/'.$item->image) }}"
                     class="card-img-top"
                     style="height:200px;object-fit:cover;"
                     alt="{{ $item->title }}">

                @endif

                <div class="card-body">

                    <small class="text-info fw-bold">

                        {{ $item->display_date->format('d M Y') }}

                    </small>

                    <h5 class="mt-2 fw-bold">

                        <a href="{{ route('frontend.news.show',$item->slug) }}"
                           class="text-dark text-decoration-none">

                            {{ $item->title }}

                        </a>

                    </h5>

                    <p class="text-muted small">

                        {{ \Illuminate\Support\Str::limit(strip_tags($item->description),80) }}

                    </p>

                </div>

            </div>

        </div>

        @endforeach

    </div>

</section>

@endif