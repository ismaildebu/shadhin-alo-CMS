@extends('frontend.layouts.app')

@section('title', ($term === '' ? 'খুঁজুন' : 'খোঁজা: '.$term).' | স্বাধীন আলো')

@section('content')

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">হোম</a></li>
        <li class="breadcrumb-item active" aria-current="page">খোঁজা ফলাফল</li>
    </ol>
</nav>

<h1 class="sa-page-title">খোঁজা ফলাফল</h1>

<form action="{{ route('frontend.search') }}" method="get" class="sa-search sa-search-page mb-4">
    <input class="sa-search-input"
           type="search"
           name="q"
           value="{{ $term }}"
           placeholder="বিষয়, ঘটনা বা মানুষের নাম লিখুন..."
           aria-label="খুঁজুন">
    <button class="sa-search-btn" type="submit">খুঁজুন</button>
</form>

<div class="row g-4">
    <div class="col-lg-8 sa-list-main">
        @if($term === '')
            <p class="text-muted text-center py-5">খুঁজতে চাইলে উপরের বক্সে কিছু লিখে দিন।</p>
        @else
            <p class="text-muted mb-3">
                "{{ $term }}" — {{ number_format($news->total()) }} টি ফলাফল।
            </p>

            @include('frontend.partials.news-cards', [
                'news' => $news,
                'cardCol' => 'col-lg-6 col-md-6',
                'emptyText' => 'এই নামে কোনো সংবাদ পাওয়া যায়নি।',
            ])

            <div class="d-flex justify-content-center my-4">
                {{ $news->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>

    <aside class="col-lg-4 sa-sidebar">
        @include('frontend.partials.sidebar')
    </aside>
</div>

@endsection
