@extends('frontend.layouts.app')

@section('title', 'ভিডিও গ্যালারি | স্বাধীন আলো')

@section('content')

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">হোম</a></li>
        <li class="breadcrumb-item active" aria-current="page">ভিডিও গ্যালারি</li>
    </ol>
</nav>

<h1 class="sa-page-title">ভিডিও গ্যালারি</h1>

<div class="row g-4">
    @forelse($videos as $video)
        <div class="col-lg-4 col-md-6">
            <a href="{{ route('frontend.video.show', $video->id) }}" class="sa-vcard d-block">
                <span class="sa-vthumb">
                    <img src="{{ $video->thumbnail }}" alt="{{ $video->title }}" loading="lazy">
                    <span class="sa-play"></span>
                </span>
                <h2 class="sa-vtitle">{{ $video->title }}</h2>
                <span class="sa-vmeta">{{ $video->published_at?->format('d M Y') }}</span>
            </a>
        </div>
    @empty
        <div class="col-12">
            <p class="text-muted text-center py-5">এই মুহূর্তে কোনো ভিডিও নেই।</p>
        </div>
    @endforelse
</div>

@if($videos->hasPages())
    <div class="d-flex justify-content-center my-4">
        {{ $videos->links('pagination::bootstrap-5') }}
    </div>
@endif

@endsection