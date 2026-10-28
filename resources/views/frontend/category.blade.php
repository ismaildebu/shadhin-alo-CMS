@extends('frontend.layouts.app')

@section('title', ($category->meta_title ?: $category->name).' | স্বাধীন আলো')

@if($category->meta_description)
    @section('meta')
        <meta name="description" content="{{ $category->meta_description }}">
    @endsection
@endif

@section('content')

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">হোম</a></li>
        @if($category->parent)
            <li class="breadcrumb-item">
                <a href="{{ route('frontend.category.show', $category->parent->slug) }}">{{ $category->parent->name }}</a>
            </li>
        @endif
        <li class="breadcrumb-item active" aria-current="page">{{ $category->name }}</li>
    </ol>
</nav>

<h1 class="sa-page-title">{{ $category->name }}</h1>

@if($children->isNotEmpty())
    <div class="mb-4">
        @foreach($children as $child)
            <a href="{{ route('frontend.category.show', $child->slug) }}" class="sa-pill">{{ $child->name }}</a>
        @endforeach
    </div>
@endif

@include('frontend.partials.news-cards', ['news' => $news, 'currentCategoryId' => $category->id])

<div class="d-flex justify-content-center my-4">
    {{ $news->links('pagination::bootstrap-5') }}
</div>

@endsection