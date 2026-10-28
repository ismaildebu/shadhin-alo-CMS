<div class="row g-4">
    @forelse($news as $item)
        <div class="col-lg-4 col-md-6">
            <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-card">
                @if($item->image)
                    <img src="{{ asset('uploads/news/'.$item->image) }}"
                         alt="{{ $item->title }}" class="sa-card-img" loading="lazy">
                @endif
                <div class="sa-card-body">
                    @if($item->category && $item->category->id !== ($currentCategoryId ?? null))
                        <span class="sa-chip mb-2">{{ $item->category->name }}</span>
                    @endif
                    <h3 class="sa-card-title">{{ $item->title }}</h3>
                    <p class="sa-card-text mb-2">
                        {{ \Illuminate\Support\Str::limit(strip_tags($item->summary ?: $item->description), 100) }}
                    </p>
                    <small class="text-muted">{{ $item->display_date->format('d M Y') }}</small>
                </div>
            </a>
        </div>
    @empty
        <div class="col-12">
            <p class="text-muted text-center py-5">{{ $emptyText ?? 'এই মুহূর্তে দেখানোর মতো কোনো সংবাদ নেই।' }}</p>
        </div>
    @endforelse
</div>