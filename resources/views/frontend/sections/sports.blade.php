@php

    $sportsCategory = $categories->firstWhere('slug', 'sports');

    $sportsNews = $sportsCategory
        ? ($categoryNews[$sportsCategory->id] ?? collect())
        : collect();

@endphp

@if($sportsNews->count())

<section class="mb-5">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">

        <h3 class="fw-bold text-success mb-0">
            ⚽ খেলা
        </h3>

        <a href="#" class="text-decoration-none fw-semibold">
            আরও »
        </a>

    </div>

    <div class="row">

        {{-- Left Lead News --}}
        <div class="col-lg-8">

            @php
                $lead = $sportsNews->first();
            @endphp

            @if($lead)

            <div class="card border-0 shadow-sm mb-3">

                @if($lead->image)
<img src="{{ asset('uploads/news/'.$lead->image) }}"
                     class="card-img-top"
                     style="height:420px;object-fit:cover;"
                     alt="{{ $lead->title }}">
@else
@include('frontend.partials.no-image', ['news' => $lead, 'sizeClass' => 'card-img-top'])
@endif

                <div class="card-body">

                    <span class="badge bg-success mb-2">
                        বড় ম্যাচ
                    </span>

                    <h2 class="fw-bold">

                        <a href="{{ route('frontend.news.show',$lead->slug) }}"
                           class="text-dark text-decoration-none">

                            {{ $lead->title }}

                        </a>

                    </h2>

                    <p class="text-muted">

                        {{ \Illuminate\Support\Str::limit(strip_tags($lead->description),160) }}

                    </p>

                </div>

            </div>

            @endif

        </div>

        {{-- Right Side --}}
        <div class="col-lg-4">

            {{-- Future Live Score --}}
            <div class="card border-success mb-3">

                <div class="card-header bg-success text-white fw-bold">
                    Live Score
                </div>

                <div class="card-body text-center text-muted">

                    ভবিষ্যতে Live Cricket / Football Score Widget

                </div>

            </div>

            @foreach($sportsNews->skip(1)->take(4) as $item)

            <div class="border-bottom pb-3 mb-3">

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

            @endforeach

        </div>

    </div>

</section>

@endif