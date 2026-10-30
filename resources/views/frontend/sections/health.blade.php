@php

    $healthCategory = $categories->firstWhere('slug', 'health');

    $healthNews = $healthCategory
        ? ($categoryNews[$healthCategory->id] ?? collect())
        : collect();

@endphp

@if($healthNews->count())

<section class="mb-5">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">

        <h3 class="fw-bold text-success mb-0">
            ❤️ স্বাস্থ্য
        </h3>

        <a href="#" class="text-decoration-none fw-semibold">
            আরও »
        </a>

    </div>

    <div class="row">

        {{-- Lead Health News --}}
        <div class="col-lg-7">

            @php
                $lead = $healthNews->first();
            @endphp

            @if($lead)

            <div class="card border-0 shadow-sm h-100">

                @if($lead->image)
<img src="{{ asset('uploads/news/'.$lead->image) }}"
                     class="card-img-top"
                     style="height:360px;object-fit:cover;"
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

                        {{ \Illuminate\Support\Str::limit(strip_tags($lead->description),160) }}

                    </p>

                </div>

            </div>

            @endif

        </div>

        {{-- Health Tips --}}
        <div class="col-lg-5">

            <div class="card shadow-sm border-0">

                <div class="card-header bg-success text-white fw-bold">

                    💡 স্বাস্থ্য টিপস

                </div>

                <div class="card-body">

                    @foreach($healthNews->skip(1)->take(5) as $item)

                    <div class="border-bottom pb-3 mb-3">

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

                    @endforeach

                </div>

            </div>

        </div>

    </div>

</section>

@endif