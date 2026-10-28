<div class="sa-block-head">
    <h2>{{ $headTitle ?? $category->name }}</h2>

    @if(($headUrl ?? null) !== false)
        <a href="{{ $headUrl ?? route('frontend.category.show', $category->slug) }}">আরও »</a>
    @endif
</div>

@if($category->slug === \App\Models\Category::NATIONWIDE_SLUG && ($divisions ?? collect())->isNotEmpty())
    <div class="mb-3">
        @foreach($divisions as $division)
            <a href="{{ $division->url() }}" class="sa-pill">{{ $division->name_bn }}</a>
        @endforeach
    </div>
@endif