@php

    $educationCategory = $categories->firstWhere('slug', 'education');

    $educationNews = $educationCategory
        ? ($categoryNews[$educationCategory->id] ?? collect())
        : collect();

@endphp

@if($educationNews->count())

<section class="mb-5">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">

        <h3 class="fw-bold text-primary mb-0">
            🎓 শিক্ষা
        </h3>

        <a href="#" class="text-decoration-none fw-semibold">
            আরও »
        </a>

    </div>

    <div class="row">

        {{-- পরীক্ষা --}}
        <div class="col-lg-4">

            <div class="card border-primary h-100">

                <div class="card-header bg-primary text-white fw-bold">
                    পরীক্ষা
                </div>

                <div class="card-body">

                    @foreach($educationNews->take(3) as $item)

                        <div class="border-bottom pb-2 mb-2">

                            <a href="{{ route('frontend.news.show',$item->slug) }}"
                               class="text-dark text-decoration-none fw-semibold">

                                {{ $item->title }}

                            </a>

                        </div>

                    @endforeach

                </div>

            </div>

        </div>

        {{-- ভর্তি --}}
        <div class="col-lg-4">

            <div class="card border-success h-100">

                <div class="card-header bg-success text-white fw-bold">
                    ভর্তি
                </div>

                <div class="card-body">

                    @foreach($educationNews->skip(3)->take(3) as $item)

                        <div class="border-bottom pb-2 mb-2">

                            <a href="{{ route('frontend.news.show',$item->slug) }}"
                               class="text-dark text-decoration-none fw-semibold">

                                {{ $item->title }}

                            </a>

                        </div>

                    @endforeach

                </div>

            </div>

        </div>

        {{-- চাকরি --}}
        <div class="col-lg-4">

            <div class="card border-danger h-100">

                <div class="card-header bg-danger text-white fw-bold">
                    চাকরি
                </div>

                <div class="card-body">

                    @foreach($educationNews->skip(6)->take(3) as $item)

                        <div class="border-bottom pb-2 mb-2">

                            <a href="{{ route('frontend.news.show',$item->slug) }}"
                               class="text-dark text-decoration-none fw-semibold">

                                {{ $item->title }}

                            </a>

                        </div>

                    @endforeach

                </div>

            </div>

        </div>

    </div>

</section>

@endif