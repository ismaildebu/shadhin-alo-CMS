@extends('frontend.layouts.app')

@section('title', $location->name_bn.($location->isDivision() ? ' বিভাগ' : '').' | স্বাধীন আলো')

@section('content')

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">হোম</a></li>
        <li class="breadcrumb-item">
            <a href="{{ route('frontend.category.show', \App\Models\Category::NATIONWIDE_SLUG) }}">সারাদেশ</a>
        </li>
        @foreach($path as $place)
            @if($loop->last)
                <li class="breadcrumb-item active" aria-current="page">{{ $place->name_bn }}</li>
            @else
                <li class="breadcrumb-item"><a href="{{ $place->url() }}">{{ $place->name_bn }}</a></li>
            @endif
        @endforeach
    </ol>
</nav>

<h1 class="sa-page-title">
    {{ $location->name_bn }}
    @if($location->isDivision())<small class="text-muted fs-5">বিভাগ</small>@endif
    @if($location->isDistrict())<small class="text-muted fs-5">জেলা</small>@endif
    @if($location->isUpazila())<small class="text-muted fs-5">উপজেলা</small>@endif
</h1>

@if($children->isNotEmpty())
    <div class="mb-4">
        <span class="text-muted me-2">{{ $location->isDivision() ? 'জেলা:' : 'উপজেলা:' }}</span>
        @foreach($children as $child)
            <a href="{{ $child->url() }}" class="sa-pill">{{ $child->name_bn }}</a>
        @endforeach
    </div>
@endif

@include('frontend.partials.news-cards', [
    'news' => $news,
    'emptyText' => 'এই এলাকার কোনো সংবাদ এখনও প্রকাশিত হয়নি।',
])

<div class="d-flex justify-content-center my-4">
    {{ $news->links('pagination::bootstrap-5') }}
</div>

@endsection