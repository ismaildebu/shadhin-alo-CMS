@extends('frontend.layouts.app')

@section('title', 'গ্রাফ ও চার্ট | '.\App\Support\Settings::get('site_name'))

@section('meta')
    <link rel="stylesheet" href="{{ asset('css/article-visuals.css') }}">
    @include('partials.chart-scripts')
@endsection

@section('content')

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">হোম</a></li>
        <li class="breadcrumb-item active" aria-current="page">গ্রাফ ও চার্ট</li>
    </ol>
</nav>

<h1 class="sa-page-title">গ্রাফ ও চার্ট</h1>

@if($charts->count())
    <div class="sa-charts-page">
        @foreach($charts as $chart)
            <article id="chart-{{ $chart->id }}">
                {!! $chart->render() !!}
            </article>
        @endforeach
    </div>

    @if($charts->hasPages())
        <div class="d-flex justify-content-center my-4">
            {{ $charts->links('pagination::bootstrap-5') }}
        </div>
    @endif
@else
    <p class="text-muted text-center py-5">এই মুহূর্তে কোনো গ্রাফ প্রকাশিত হয়নি।</p>
@endif

@endsection
