@php
    $newsList = $categoryNews[$category->id] ?? collect();
@endphp

@if($newsList->count())

<section class="mb-5">

    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom border-2">

        <h3 class="fw-bold text-success mb-2">
            {{ $category->name }}
        </h3>

        <a href="#" class="text-decoration-none">
            আরও »
        </a>

    </div>

    <div class="row">

        {{-- Left Big News --}}
        <div class="col-lg-6">

            @php
                $first = $newsList->first();
            @endphp

            @if($first)

                @if($first->image)
<img src="{{ asset('uploads/news/'.$first->image) }}"
                         class="img-fluid rounded mb-3"
                         style="height:280px;width:100%;object-fit:cover;"
                         alt="{{ $first->title }}">
@else
@include('frontend.partials.no-image', ['news' => $first, 'sizeClass' => 'img-fluid rounded mb-3'])
@endif

                <h4>

                    <a href="{{ route('frontend.news.show',$first->slug) }}"
                       class="text-dark text-decoration-none">

                        {{ $first->title }}

                    </a>

                </h4>

                <p class="text-muted">

                    {{ \Illuminate\Support\Str::limit(strip_tags($first->description),140) }}

                </p>

            @endif

        </div>

        {{-- Right News List --}}
        <div class="col-lg-6">

            @foreach($newsList->skip(1) as $item)

                <div class="border-bottom pb-3 mb-3">

                    <a href="{{ route('frontend.news.show',$item->slug) }}"
                       class="fw-semibold text-dark text-decoration-none">

                        {{ $item->title }}

                    </a>

                    <div class="small text-muted mt-1">
                        {{ $item->display_date->format('d M Y') }}
                    </div>

                </div>

            @endforeach

        </div>

    </div>

</section>

@endif