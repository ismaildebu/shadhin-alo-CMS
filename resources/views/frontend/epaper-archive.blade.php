@extends('frontend.layouts.app')

@section('title', 'ই-পেপার আর্কাইভ | স্বাধীন আলো')

@section('content')

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">হোম</a></li>
        <li class="breadcrumb-item active" aria-current="page">ই-পেপার</li>
    </ol>
</nav>

<h1 class="sa-page-title">ই-পেপার আর্কাইভ</h1>

<div class="row g-4">
    <div class="col-lg-8 sa-list-main">

        @forelse($pages as $page)
            <a class="sa-epaper-arch" href="{{ route('frontend.epaper.show', $page->edition_date->toDateString()) }}">
                <span class="sa-epaper-arch-date">{{ $page->edition_date->format('d F Y, l') }}</span>
                <span class="sa-epaper-arch-count">{{ number_format($page->blockCount()) }}টি সংবাদ</span>
                <span class="sa-epaper-arch-go">পাতা দেখুন &rarr;</span>
            </a>
        @empty
            <p class="text-muted text-center py-5">এখনো কোনো ই-পেপার প্রকাশিত হয়নি।</p>
        @endforelse

        @if($pages->hasPages())
            <div class="d-flex justify-content-center my-4">
                {{ $pages->links('pagination::bootstrap-5') }}
            </div>
        @endif

    </div>

    <aside class="col-lg-4 sa-sidebar">
        @include('frontend.partials.sidebar')
    </aside>
</div>

@endsection
