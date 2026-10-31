@if($featuredNews->count())

<section class="mb-5">

    <div class="sa-block-head">
        <h2>নির্বাচিত সংবাদ</h2>
    </div>

    <div class="row g-4">
                @foreach($featuredNews->take($limit ?? 4) as $item)
            <div class="col-lg-3 col-md-6">
                <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-card">
                    @if($item->image)
<img src="{{ \App\Models\Media::thumbUrl($item->image, 800) }}"
                             alt="{{ $item->title }}" class="sa-feat-img" loading="lazy">
@else
@include('frontend.partials.no-image', ['news' => $item, 'sizeClass' => 'sa-feat-img'])
@endif
                    <div class="sa-card-body">
                        <span class="sa-chip">{{ $item->category->name ?? '' }}</span>
                        <h3 class="sa-feat-title">{{ \Illuminate\Support\Str::limit($item->title, 75) }}</h3>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

</section>

@endif