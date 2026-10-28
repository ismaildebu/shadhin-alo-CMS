@extends('frontend.layouts.app')

@section('title', 'ট্যাগ: '.$tag->name.' | স্বাধীন আলো')

@section('content')

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">হোম</a></li>
        <li class="breadcrumb-item active" aria-current="page">ট্যাগ</li>
    </ol>
</nav>

<h1 class="sa-page-title">
    <span class="text-muted fs-5">ট্যাগ:</span> {{ $tag->name }}
</h1>

@include('frontend.partials.news-cards', ['news' => $news])

<div class="d-flex justify-content-center my-4">
    {{ $news->links('pagination::bootstrap-5') }}
</div>

@endsection