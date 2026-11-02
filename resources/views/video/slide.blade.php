<!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    *{box-sizing:border-box;margin:0;padding:0}
    html,body{width:1080px;height:1920px;overflow:hidden;background:#0b2745}
    body{font-family:'Noto Sans Bengali',system-ui,sans-serif;color:#fff;-webkit-font-smoothing:antialiased}
    .slide{
        position:relative;width:1080px;height:1920px;padding:76px 72px 92px;
        display:flex;flex-direction:column;overflow:hidden;
        background:
            radial-gradient(1100px 780px at 100% 0%,#1c528c 0%,rgba(28,82,140,0) 62%),
            linear-gradient(165deg,#0b2745 0%,#123b68 58%,#0d2c52 100%);
    }
    .slide::after{content:"";position:absolute;left:0;bottom:0;width:300px;height:14px;background:#d60000}
    .brand{display:flex;align-items:center;justify-content:space-between;gap:30px;padding-bottom:24px;border-bottom:1px solid rgba(255,255,255,.16)}
    .brand-name{position:relative;padding-left:22px;font-size:37px;font-weight:700;line-height:1.2}
    .brand-name::before{content:"";position:absolute;left:0;top:5px;width:10px;height:33px;border-radius:4px;background:#d60000}
    .tagline{font-size:23px;color:rgba(255,255,255,.6);text-align:right}
    .kicker{margin-top:58px;align-self:flex-start;background:#d60000;font-size:27px;font-weight:600;letter-spacing:.02em;padding:9px 26px;border-radius:999px}
    h1{margin-top:32px;font-size:80px;line-height:1.24;font-weight:700;letter-spacing:-.01em;text-wrap:balance}
    .date{margin-top:24px;font-size:29px;color:rgba(255,255,255,.66)}
    .lead{margin-top:26px;font-size:31px;line-height:1.55;color:rgba(255,255,255,.7);max-height:100px;overflow:hidden}
    .visual{margin-top:auto;margin-bottom:auto;height:700px;border-radius:30px;overflow:hidden;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.05);display:flex;align-items:center;justify-content:center}
    .visual img{display:block;width:100%;height:100%;object-fit:cover}
    .visual.chart{background:#f7fafc;padding:40px 34px}
    .visual.chart svg{display:block;width:100%;height:auto}
    .placeholder{width:100%;height:100%;display:flex;align-items:flex-end;justify-content:center;gap:34px;padding:0 90px 70px}
    .placeholder span{display:block;width:110px;border-radius:18px 18px 6px 6px;background:rgba(255,255,255,.14)}
    .placeholder span:nth-child(1){height:38%}
    .placeholder span:nth-child(2){height:62%}
    .placeholder span:nth-child(3){height:86%;background:rgba(214,0,0,.55)}
    .context{margin-top:46px;font-size:33px;line-height:1.45;color:rgba(255,255,255,.62);font-weight:500;max-height:150px;overflow:hidden}
    .label{display:flex;align-items:center;gap:18px;margin-top:44px;font-size:29px;font-weight:600;color:#ffb3b3;letter-spacing:.04em}
    .label::after{content:"";flex:1;height:1px;background:rgba(255,255,255,.14)}
    .summary-body{margin-top:42px;font-size:50px;line-height:1.62;font-weight:500;color:#eef4fb;max-height:1160px;overflow:hidden}
    .summary-foot{margin-top:auto;padding-top:30px;border-top:1px solid rgba(255,255,255,.14);font-size:27px;color:rgba(255,255,255,.55);letter-spacing:.02em}
    .chart-stage{margin-top:auto;margin-bottom:auto}
    .chart-card{background:#f7fafc;border-radius:30px;padding:46px 34px;box-shadow:0 30px 80px rgba(0,0,0,.28)}
    .chart-card svg{display:block;width:100%;height:auto}
    .caption{margin-top:26px;font-size:24px;color:rgba(255,255,255,.55)}
    .chart-note{margin-top:16px;font-size:28px;line-height:1.6;color:rgba(255,255,255,.6);max-height:95px;overflow:hidden}
    .closing{justify-content:center;align-items:center;text-align:center}
    .closing .mark{display:flex;align-items:center;justify-content:center;width:158px;height:158px;border-radius:40px;background:#d60000;font-size:76px;font-weight:700;margin-bottom:56px;box-shadow:0 26px 60px rgba(214,0,0,.32)}
    .closing h2{font-size:64px;font-weight:700;line-height:1.3}
    .closing .site{margin-top:30px;font-size:44px;font-weight:600}
    .closing .tag{margin-top:12px;font-size:28px;color:rgba(255,255,255,.64)}
    .closing .url{margin-top:54px;font-size:27px;color:#a8c9ea;padding:14px 34px;border:1px solid rgba(255,255,255,.22);border-radius:999px;max-width:900px;word-break:break-all}
</style>
</head>
<body>
<div class="slide slide-{{ $kind }}{{ $kind === 'closing' ? ' closing' : '' }}">

    @if($kind === 'cover')
        <header class="brand">
            <span class="brand-name">{{ $site }}</span>
            @if($tagline !== '')<span class="tagline">{{ $tagline }}</span>@endif
        </header>

        @if($category !== '')<span class="kicker">{{ $category }}</span>@endif

        <h1>{{ $title }}</h1>
        <div class="date">{{ $date }}</div>
        @if($excerpt !== '')
            <p class="lead">{{ \Illuminate\Support\Str::limit($excerpt, 120, '…') }}</p>
        @endif

        <div class="visual {{ $visual === 'chart' ? 'chart' : '' }}">
            @if($visual === 'image')
                <img src="{{ $image }}" alt="">
            @elseif($visual === 'chart')
                {!! $chart !!}
            @else
                <div class="placeholder"><span></span><span></span><span></span></div>
            @endif
        </div>
    @endif

    @if($kind === 'summary')
        <header class="brand">
            <span class="brand-name">{{ $site }}</span>
            @if($tagline !== '')<span class="tagline">{{ $tagline }}</span>@endif
        </header>

        <div class="context">{{ $title }}</div>
        <div class="label">সংক্ষেপে</div>
        <p class="summary-body">{{ $excerpt }}</p>
        <div class="summary-foot">{{ $category !== '' ? $category.' · ' : '' }}{{ $date }}</div>
    @endif

    @if($kind === 'chart')
        <header class="brand">
            <span class="brand-name">{{ $site }}</span>
            @if($tagline !== '')<span class="tagline">{{ $tagline }}</span>@endif
        </header>

        <div class="context">{{ $title }}</div>
        <div class="label">গ্রাফে দেখুন</div>

        <div class="chart-stage">
            <div class="chart-card">{!! $chart !!}</div>
            <div class="caption">সূত্র: {{ $site }}</div>
            @if($excerpt !== '')
                <p class="chart-note">{{ \Illuminate\Support\Str::limit($excerpt, 110, '…') }}</p>
            @endif
        </div>
    @endif

    @if($kind === 'closing')
        <div class="mark">আ</div>
        <h2>বিস্তারিত পড়ুন</h2>
        <div class="site">{{ $site }}</div>
        @if($tagline !== '')<div class="tag">{{ $tagline }}</div>@endif
        @if($url)<div class="url">{{ $url }}</div>@endif
    @endif

</div>
</body>
</html>
