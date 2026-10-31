<div class="row">
    @forelse($news as $item)
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                @if($item->image)
                    <a href="{{ route('frontend.news.show', $item->slug) }}">
                        <img src="{{ \App\Models\Media::thumbUrl($item->image, 400) }}"
                             class="card-img-top" alt="{{ $item->title }}"
                             style="height:180px;object-fit:cover;">
                    </a>
                @endif
                <div class="card-body">
                    @if(! isset($currentCategoryId) || $item->category_id !== $currentCategoryId)
                        <span class="badge bg-success mb-2">{{ $item->category->name }}</span>
                    @endif
                    <h5 class="card-title">
                        <a href="{{ route('frontend.news.show', $item->slug) }}" class="text-dark text-decoration-none">
                            {{ $item->title }}
                        </a>
                    </h5>
                    @if($item->summary)
                        <p class="card-text text-muted small">{{ \Illuminate\Support\Str::limit($item->summary, 120) }}</p>
                    @endif
                </div>
                <div class="card-footer bg-white border-0 text-muted small">
                    {{ $item->display_date->format('d M Y') }}
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <p class="text-muted">{{ $emptyText ?? 'এই বিভাগে এখনও কোনো সংবাদ নেই।' }}</p>
        </div>
    @endforelse
</div>
