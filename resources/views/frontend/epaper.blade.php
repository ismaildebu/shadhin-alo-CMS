@extends('frontend.layouts.app')

@php
    $edition = $page->edition_date->timezone('Asia/Dhaka');
    $site = \App\Support\Settings::all();

    $flat = collect($plan['columns'])->flatMap(fn ($column) => $column['blocks']);
    $leadId = $flat->first()['news_id'] ?? null;
    $total = $flat->count();
@endphp

@section('title', 'ই-পেপার · '.$edition->format('d M Y').' | স্বাধীন আলো')

@section('meta')
    <meta name="description" content="{{ $site['site_name'] }} এর {{ $edition->format('d F Y') }} তারিখের ই-পেপার।">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+Bengali:wght@400;600;700&display=swap" rel="stylesheet">
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

<section class="sa-epaper-print">

    <header class="sa-epaper-masthead">
        <a class="sa-epaper-mast-logo" href="{{ route('home') }}">
            <img src="{{ \App\Support\Settings::logoUrl() }}" alt="{{ $site['site_name'] }}">
        </a>

        <div class="sa-epaper-mast-text">
            <div class="sa-epaper-mast-name">{{ $site['site_name'] }}</div>
            <div class="sa-epaper-mast-tag">{{ $site['site_tagline'] }}</div>
        </div>

        <div class="sa-epaper-mast-edition">
            <span class="sa-epaper-mast-day">{{ \App\Support\BanglaTime::weekday($edition) }}</span>
            <span class="sa-epaper-mast-date">{{ \App\Support\BanglaTime::date($edition) }}</span>
            <span class="sa-epaper-mast-kind">ই-পেপার</span>
        </div>
    </header>

    @if($total > 1)
        <div class="sa-epaper-teasers">
            @foreach($flat->take(4) as $block)
                @php $item = $news[$block['news_id']]; @endphp
                <a class="sa-epaper-teaser" href="{{ route('frontend.news.show', $item->slug) }}">
                    <span class="sa-epaper-teaser-title">{{ $item->title }}</span>
                    <span class="sa-epaper-teaser-go">&raquo;&raquo; পড়ুন</span>
                </a>
            @endforeach
        </div>
    @endif

    <div class="sa-epaper-strip">
        <span>{{ \App\Support\BanglaTime::weekday($edition) }}। {{ \App\Support\BanglaTime::date($edition) }}</span>
        <span>{{ \App\Support\BanglaTime::digits($total) }}টি সংবাদ</span>
        <a href="{{ route('frontend.epaper.show', $edition->toDateString()) }}">e-paper</a>
    </div>

    @if($total > 0)
        <div class="sa-epaper">
            @foreach($plan['columns'] as $column)
                <div class="sa-epaper-col" style="flex-grow:{{ $column['width'] }}">
                    @foreach($column['blocks'] as $block)
                        @php $item = $news[$block['news_id']]; @endphp
                        @include('frontend.partials.epaper-block', [
                            'item' => $item,
                            'block' => $block,
                            'leadId' => $leadId,
                        ])
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

@endsection
