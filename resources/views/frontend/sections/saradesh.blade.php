@php

    $saradeshCategory = $categories->firstWhere('slug', 'saradesh');

    $saradeshNews = $saradeshCategory
        ? ($categoryNews[$saradeshCategory->id] ?? collect())
        : collect();

@endphp

@if($saradeshNews->count())

<section class="mb-5">

    {{-- Section Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">

        <h3 class="fw-bold text-success mb-0">
            সারাদেশ
        </h3>

        <a href="#" class="text-decoration-none fw-semibold">
            আরও »
        </a>

    </div>

    <div class="row">

        @foreach($saradeshNews->take(4) as $item)

        <div class="col-lg-6 col-md-6 mb-4">

            <div class="card border-0 shadow-sm h-100">

                @if($item->image)
<img src="{{ \App\Models\Media::thumbUrl($item->image, 400) }}"
                     class="card-img-top"
                     style="height:220px;object-fit:cover;"
                     alt="{{ $item->title }}">
@else
@include('frontend.partials.no-image', ['news' => $item, 'sizeClass' => 'card-img-top'])
@endif

                <div class="card-body">

                    <h5 class="fw-bold lh-base">

                        <a href="{{ route('frontend.news.show',$item->slug) }}"
                           class="text-dark text-decoration-none">

                            {{ $item->title }}

                        </a>

                    </h5>

                    <p class="text-muted mb-2">

                        {{ \Illuminate\Support\Str::limit(strip_tags($item->description),80) }}

                    </p>

                    <small class="text-secondary">

                        {{ $item->reporter }}

                        |

                        {{ $item->display_date->format('d M Y') }}

                    </small>

                </div>

            </div>

        </div>

        @endforeach

    </div>

</section>

@endif