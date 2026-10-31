@php

    $opinionCategory = $categories->firstWhere('slug', 'opinion');

    $opinionNews = $opinionCategory
        ? ($categoryNews[$opinionCategory->id] ?? collect())
        : collect();

@endphp

@if($opinionNews->count())

<section class="mb-5">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">

        <h3 class="fw-bold text-dark mb-0">
            ✍️ মতামত
        </h3>

        <a href="#" class="text-decoration-none fw-semibold">
            আরও »
        </a>

    </div>

    <div class="row">

        @foreach($opinionNews->take(4) as $item)

        <div class="col-lg-3 col-md-6 mb-4">

            <div class="card border-0 shadow-sm text-center h-100">

                <div class="pt-4">

                    @if($item->image)

                        <img src="{{ \App\Models\Media::thumbUrl($item->image, 400) }}"
                             class="rounded-circle border"
                             style="width:110px;height:110px;object-fit:cover;"
                             alt="{{ $item->title }}">

                    @else

                        <div class="rounded-circle bg-light border d-inline-flex align-items-center justify-content-center"
                             style="width:110px;height:110px;">

                            👤

                        </div>

                    @endif

                </div>

                <div class="card-body">

                    <small class="text-secondary fw-bold">

                        {{ $item->reporter }}

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