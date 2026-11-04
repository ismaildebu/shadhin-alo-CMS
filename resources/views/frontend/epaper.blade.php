@extends('frontend.layouts.app')

@php
    $edition = $page->edition_date->timezone('Asia/Dhaka');
    $site = \App\Support\Settings::all();
@endphp

@section('title', 'ই-পেপার · '.$edition->format('d M Y').' | স্বাধীন আলো')

@section('meta')
    <meta name="description" content="{{ $site['site_name'] }} এর {{ $edition->format('d F Y') }} তারিখের ই-পেপার।">
@endsection

@section('content')

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">হোম</a></li>
        <li class="breadcrumb-item"><a href="{{ route('frontend.epaper.archive') }}">ই-পেপার</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $edition->format('d M Y') }}</li>
    </ol>
</nav>

<h1 class="sa-page-title">ই-পেপার</h1>

<div class="row g-4">
    <div class="col-lg-8 sa-list-main">

        <section class="sa-epaper-print">

            <header class="sa-epaper-masthead">
                <a href="{{ route('home') }}"><img src="{{ \App\Support\Settings::logoUrl() }}" alt="{{ $site['site_name'] }}" class="sa-epaper-logo"></a>
                <div class="sa-epaper-mast-text">
                    <div class="sa-epaper-mast-name">{{ $site['site_name'] }}</div>
                    <div class="sa-epaper-mast-meta">{{ $edition->format('d F Y, l') }}</div>
                </div>
                <div class="sa-epaper-mast-tag">ই-পেপার</div>
            </header>

            @php $hasBlocks = collect($plan['columns'])->contains(fn ($column) => $column['blocks'] !== []); @endphp

            @if($hasBlocks)
                <div class="sa-epaper">
                    @foreach($plan['columns'] as $column)
                        <div class="sa-epaper-col" style="flex-grow:{{ $column['width'] }}">
                            @foreach($column['blocks'] as $block)
                                @php $item = $news[$block['news_id']]; @endphp
                                @include('frontend.partials.epaper-block', ['item' => $item, 'block' => $block])
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-muted text-center py-5 mb-0">এই পাতায় এখনো কোনো সংবাদ বসানো হয়নি।</p>
            @endif

            <footer class="sa-epaper-foot">
                <a href="{{ route('frontend.epaper.index') }}">আজকের পাতা</a>
                <a href="{{ route('frontend.epaper.archive') }}">পুরোনো সব সংস্করণ</a>
            </footer>

        </section>

    </div>

    <aside class="col-lg-4 sa-sidebar">
        @include('frontend.partials.sidebar')
    </aside>
</div>

@endsection
