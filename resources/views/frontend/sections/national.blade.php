@php

$nationalCategory = $categories->firstWhere('slug', 'jatiyo');

$nationalNews = $nationalCategory
    ? ($categoryNews[$nationalCategory->id] ?? collect())
    : collect();

@endphp

@if($nationalNews->count())

<section class="mb-5">

    {{-- Section Header --}}
    <div class="d-flex justify-content-between align-items-center border-bottom border-3 border-danger pb-2 mb-4">

        <h3 class="fw-bold text-danger mb-0">
            জাতীয়
        </h3>

        <a href="#" class="text-success fw-bold text-decoration-none">
            আরও »
        </a>

    </div>

    <div class="row">

        {{-- Lead News --}}
        <div class="col-lg-7">

            @php
                $lead = $nationalNews->first();
            @endphp

            @if($lead)

            <article class="card border-0 shadow-sm national-card h-100">

                @if($lead->image)

                <div class="overflow-hidden">

                    <img src="{{ asset('uploads/news/'.$lead->image) }}"
                         class="card-img-top national-image"
                         alt="{{ $lead->title }}">

                </div>

                @endif

                <div class="card-body">

                    <span class="badge bg-danger mb-3">
                        জাতীয়
                    </span>

                    <h2 class="fw-bold lh-base">

                        <a href="{{ route('frontend.news.show',$lead->slug) }}"
                           class="text-dark text-decoration-none title-link">

                            {{ $lead->title }}

                        </a>

                    </h2>

                    <div class="small text-muted mb-3">

                        <strong>{{ $lead->reporter ?? 'স্বাধীন আলো' }}</strong>

                        |

                        {{ $lead->display_date->format('d M Y') }}

                    </div>

                    <p class="text-secondary">

                        {{ \Illuminate\Support\Str::limit(strip_tags($lead->description),220) }}

                    </p>

                    <a href="{{ route('frontend.news.show',$lead->slug) }}"
                       class="btn btn-sm btn-success">

                        বিস্তারিত পড়ুন →

                    </a>

                </div>

            </article>

            @endif

        </div>

        {{-- Right News --}}
        <div class="col-lg-5">

            @foreach($nationalNews->skip(1)->take(4) as $item)

            <article class="d-flex mb-3 pb-3 border-bottom national-item">

                <div style="width:120px;flex-shrink:0;">

                    @if($item->image)
<img src="{{ asset('uploads/news/'.$item->image) }}"
                         class="img-fluid rounded national-thumb"
                         alt="{{ $item->title }}">
@else
@include('frontend.partials.no-image', ['news' => $item, 'sizeClass' => 'img-fluid rounded national-thumb'])
@endif

                </div>

                <div class="ps-3">

                    <h6 class="fw-bold">

                        <a href="{{ route('frontend.news.show',$item->slug) }}"
                           class="text-dark text-decoration-none title-link">

                            {{ $item->title }}

                        </a>

                    </h6>

                    <small class="text-muted">

                        {{ $item->display_date->format('d M Y') }}

                    </small>

                </div>

            </article>

            @endforeach

        </div>

    </div>

</section>

<style>

.national-card{
    transition:.3s;
}

.national-card:hover{
    transform:translateY(-4px);
    box-shadow:0 .5rem 1rem rgba(0,0,0,.15)!important;
}

.national-image{
    height:420px;
    width:100%;
    object-fit:cover;
    transition:.4s;
}

.national-card:hover .national-image{
    transform:scale(1.05);
}

.national-thumb{
    width:120px;
    height:85px;
    object-fit:cover;
    transition:.3s;
}

.national-item:hover .national-thumb{
    transform:scale(1.05);
}

.title-link{
    transition:.3s;
}

.title-link:hover{
    color:#007a33!important;
}

</style>

@endif