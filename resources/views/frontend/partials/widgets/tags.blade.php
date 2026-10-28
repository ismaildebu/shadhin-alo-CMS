@php
    $tagList = ($popularTags ?? collect())->take(max(4, min(20, (int) ($limit ?? 12))));
@endphp

@if($tagList->isNotEmpty())
<div class="sa-widget">
    <div class="sa-widget-head">জনপ্রিয় ট্যাগ</div>
    <div class="p-3">
        @foreach($tagList as $popularTag)
            <a href="{{ route('frontend.tag.show', $popularTag->slug) }}" class="sa-tag">{{ $popularTag->name }}</a>
        @endforeach
    </div>
</div>
@endif