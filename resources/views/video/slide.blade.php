<!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;500;600;700&display=block" rel="stylesheet">
<style>
    *{box-sizing:border-box;margin:0;padding:0}
    /* প্রতিটি এনিমেশন ধাপ = একটি ১০৮০x১৯২০ ফ্রেম; ধাপগুলো লম্বালম্বি সাজানো থাকে (filmstrip) */
    html,body{width:1080px;overflow:hidden;background:#07101d}
    body{font-family:'Noto Sans Bengali','Nirmala UI','Shonar Bangla','Vrinda',system-ui,sans-serif;
        color:#fff;-webkit-font-smoothing:antialiased;min-height:1920px}
    .strip{display:flex;flex-direction:column;width:1080px}
    .frame{position:relative;width:1080px;height:1920px;overflow:hidden;flex:0 0 auto}
    .layer{position:absolute;inset:0;pointer-events:none}
    .layer.dots{background-image:radial-gradient(rgba(255,255,255,.055) 2px,transparent 2px);background-size:54px 54px}
    .layer.vignette{background:radial-gradient(120% 80% at 50% 118%,rgba(0,0,0,.42) 0%,rgba(0,0,0,0) 60%)}

    /* শুধু লোগো—ডান উপরের কোনায়; তারিখ বা বাকি কিছু নেই */
    .corner{position:absolute;top:70px;right:70px;z-index:6;width:104px;height:104px;border-radius:30px;
        background:rgba(255,255,255,.95);display:flex;align-items:center;justify-content:center;
        overflow:hidden;box-shadow:0 16px 40px rgba(0,0,0,.34)}
    .corner img{display:block;width:100%;height:100%;object-fit:contain;padding:13px}
    .corner span{font-size:46px;font-weight:700;color:var(--accent,#d60000)}

    /* রিলসের ওপর-নিচ ইউআই অংশ ফাঁকা রাখে */
    .content{position:relative;z-index:2;height:100%;display:flex;flex-direction:column;padding:264px 80px 320px}

    .chip{align-self:flex-start;background:var(--accent,#d60000);color:#fff;font-size:28px;font-weight:600;
        line-height:1;padding:15px 32px;border-radius:999px;max-width:100%;overflow:hidden;white-space:nowrap}
    .subline{font-size:40px;line-height:1.4;font-weight:600;color:rgba(255,255,255,.78);max-height:180px;overflow:hidden}
    .label{display:flex;align-items:center;gap:24px;font-size:29px;font-weight:600;letter-spacing:.05em;
        color:var(--accent-tint,#ffb3b3);flex:0 0 auto}
    .label::after{content:"";flex:1;height:1px;background:rgba(255,255,255,.18)}
    .stack{margin:auto 0;display:flex;flex-direction:column;min-height:0;gap:36px}

    .hot{color:var(--accent-tint,#ffb3b3)}

    .head{font-weight:700;letter-spacing:-.012em;line-height:1.26;max-height:560px;overflow:hidden;font-size:92px}
    .photo{margin:44px -80px -320px;flex:1;min-height:0;border-radius:38px 38px 0 0;overflow:hidden;position:relative;
        border-top:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.05)}
    .photo::before{content:"";position:absolute;inset:0 0 auto;height:190px;z-index:2;
        background:linear-gradient(180deg,rgba(0,0,0,.34) 0%,rgba(0,0,0,0) 100%)}
    .photo img{display:block;width:100%;height:100%;object-fit:cover;object-position:50% 22%}
    .photo i{position:absolute;border-radius:50%;border:2px solid rgba(255,255,255,.12)}
    .photo i:nth-child(1){width:620px;height:620px;left:-160px;top:-200px}
    .photo i:nth-child(2){width:440px;height:440px;right:-120px;bottom:-140px;background:rgba(255,255,255,.05);border:0}
    .photo i:nth-child(3){width:250px;height:250px;right:120px;top:120px;background:var(--accent-soft,rgba(214,0,0,.35));border:0;filter:blur(4px)}

    /* lead — বড় লেখা, ধাপে ধাপে জ্বলে ওঠে (সীমার ভেতরে বসে এমন অক্ষর খোঁজে) */
    .para{font-weight:500;color:#eef4fb;overflow:hidden;max-height:1120px;font-size:56px}

    /* rail — বাঁ পাশে অ্যাকসেন্ট রেখার কার্ড */
    .panel{background:rgba(255,255,255,.07);border-left:10px solid var(--accent,#d60000);
        border-radius:0 30px 30px 0;padding:52px 46px;box-shadow:0 26px 60px rgba(0,0,0,.28)}
    .panel .para{font-size:50px;color:#f4f8fd}

    /* quote — উদ্ধৃতি চিহ্নসহ বড় লেখা */
    .quote{margin:auto 0;display:flex;flex-direction:column;gap:22px}
    .qmark{font-size:220px;line-height:.55;font-weight:700;color:var(--accent,#d60000);height:110px}
    .quote .para{font-size:64px;font-weight:600;color:#fff}

    /* points — ক্রমিক নম্বরসহ তালিকা */
    .list{display:flex;flex-direction:column;gap:30px;list-style:none;font-size:48px;max-height:1180px;overflow:hidden}
    .point{display:flex;align-items:flex-start;gap:26px;font-weight:500;line-height:1.46;color:#eef4fb}
    .point b{flex:0 0 auto;min-width:1.55em;height:1.55em;padding:0 .25em;border-radius:.45em;background:var(--accent,#d60000);
        color:#fff;font-size:.72em;font-weight:700;display:flex;align-items:center;justify-content:center}

    /* chart */
    .note{font-size:44px;font-weight:600;color:rgba(255,255,255,.92);max-height:240px;overflow:hidden}
    .chart-stage{margin:36px -80px -320px;flex:1;min-height:0;display:flex}
    .chart-card{flex:1;min-height:0;background:#f7fafc;border-radius:38px 38px 0 0;padding:46px 34px 30px;
        box-shadow:0 -30px 70px rgba(0,0,0,.4);display:flex;flex-direction:column}
    .chart-holder{flex:1;min-height:0;display:flex;align-items:center;justify-content:center}
    .chart-holder svg{display:block;max-width:100%}
    .source{margin-top:20px;flex:0 0 auto;font-size:22px;color:#7b8794;text-align:right}

    /* closing */
    .content.closing{align-items:center;justify-content:center;text-align:center}
    .mark{display:flex;align-items:center;justify-content:center;width:190px;height:190px;border-radius:50px;
        background:#fff;font-size:88px;font-weight:700;box-shadow:0 30px 70px rgba(0,0,0,.42)}
    .mark img{width:120px;height:120px;object-fit:contain}
    .closing .stack{align-items:center;gap:26px}
    .closing .para{font-size:54px;font-weight:600;color:#fff}
    .closing .site{font-size:48px;font-weight:700}
    .closing .tag{font-size:30px;color:rgba(255,255,255,.68)}
    .closing .url{font-size:28px;color:#dbe9f8;padding:18px 40px;border-radius:999px;
        border:1px solid rgba(255,255,255,.24);background:rgba(255,255,255,.07);max-width:900px;word-break:break-all}
</style>
</head>
<body>
<div class="strip" id="strip">
    <div class="frame" id="frame" data-accent="{{ $accent }}">
        <div class="layer dots"></div>
        <div class="layer vignette"></div>
        <div class="corner">
            @if($logo)<img src="{{ $logo }}" alt="">@else<span>{{ \Illuminate\Support\Str::substr($site, 0, 1) }}</span>@endif
        </div>

        <div class="content{{ $kind === 'closing' ? ' closing' : '' }}">
            @if($kind === 'cover')
                <div class="stack">
                    @if($category !== '')<span class="chip" data-rv>{{ $category }}</span>@endif
                    @if($subline !== '')<div class="subline" data-rv>{{ $subline }}</div>@endif
                    <div class="head">{!! $headline !!}</div>
                </div>

                <div class="photo" data-rv>
                    @if($visual === 'image')
                        <img src="{{ $image }}" alt="">
                    @else
                        <i></i><i></i><i></i>
                    @endif
                </div>
            @elseif($kind === 'closing')
                <div class="stack">
                    <div class="mark" data-rv>
                        @if($logo)<img src="{{ $logo }}" alt="">@else{{ \Illuminate\Support\Str::substr($site, 0, 1) }}@endif
                    </div>
                    @if($text !== '')<p class="para">{!! $text !!}</p>@endif
                    <div class="site" data-rv>{{ $site }}</div>
                    @if($tagline !== '')<div class="tag" data-rv>{{ $tagline }}</div>@endif
                    @if($url)<div class="url" data-rv>{{ $url }}</div>@endif
                </div>
            @elseif($kind === 'chart')
                @if($label !== '')<div class="label" data-rv>{{ $label }}</div>@endif
                @if($text !== '')<p class="note">{!! $text !!}</p>@endif

                <div class="chart-stage">
                    <div class="chart-card" data-rv>
                        <div class="chart-holder">{!! $chart !!}</div>
                        <div class="source">সূত্র: {{ $site }}</div>
                    </div>
                </div>
            @elseif($kind === 'points')
                <div class="stack">
                    @if($label !== '')<div class="label" data-rv>{{ $label }}</div>@endif
                    <ul class="list">
                        @foreach($items as $item)
                            <li class="point" data-rv><b>{{ $item['n'] }}</b><span>{!! $item['text'] !!}</span></li>
                        @endforeach
                    </ul>
                </div>
            @elseif($kind === 'quote')
                <div class="quote">
                    <div class="qmark" data-rv>&rdquo;</div>
                    <p class="para">{!! $text !!}</p>
                </div>
            @elseif($kind === 'rail')
                <div class="stack">
                    @if($label !== '')<div class="label" data-rv>{{ $label }}</div>@endif
                    <div class="panel"><p class="para">{!! $text !!}</p></div>
                </div>
            @else
                <div class="stack">
                    @if($label !== '')<div class="label" data-rv>{{ $label }}</div>@endif
                    <p class="para">{!! $text !!}</p>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
    function rgb(hex) {
        const h = String(hex).replace('#', '');
        const f = h.length === 3 ? h.replace(/./g, function (c) { return c + c; }) : h;
        const n = parseInt(f.slice(0, 6), 16);

        return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
    }

    function mix(hex, target, t) {
        const c = rgb(hex);

        return 'rgb(' + c.map(function (v, i) {
            return Math.round(v + (target[i] - v) * t);
        }).join(',') + ')';
    }

    const template = document.getElementById('frame');
    const strip = document.getElementById('strip');
    const ACCENT = template.dataset.accent || '#d60000';
    const RGB = rgb(ACCENT);
    const root = document.documentElement.style;

    root.setProperty('--accent', ACCENT);
    root.setProperty('--accent-tint', mix(ACCENT, [255, 255, 255], 0.55));
    root.setProperty('--accent-soft', 'rgba(' + RGB.join(',') + ',.42)');

    const BG = 'radial-gradient(1150px 820px at 100% -6%,rgba(' + RGB.join(',') + ',.34) 0%,rgba(' +
        RGB.join(',') + ',0) 62%),linear-gradient(163deg,' + mix(ACCENT, [0, 0, 0], 0.32) + ' 0%,' +
        mix(ACCENT, [0, 0, 0], 0.66) + ' 54%,' + mix(ACCENT, [0, 0, 0], 0.9) + ' 100%)';

    // বড় অক্ষর থেকে শুরু: যতক্ষণ না লেখা বক্সের ভেতরে বসে
    function fitBlock(el, max, min, ratio) {
        let lo = min, hi = max, size = min;

        while (lo <= hi) {
            const mid = (lo + hi) >> 1;

            el.style.fontSize = mid + 'px';
            el.style.lineHeight = Math.round(mid * ratio) + 'px';

            if (el.scrollHeight > el.clientHeight) {
                hi = mid - 1;
            } else {
                size = mid;
                lo = mid + 1;
            }
        }

        el.style.fontSize = size + 'px';
        el.style.lineHeight = Math.round(size * ratio) + 'px';
    }

    function fitChart(box) {
        const svg = box.querySelector('svg');

        if (!svg) { return; }

        svg.style.width = '100%';
        svg.style.height = 'auto';

        if (svg.getBoundingClientRect().height > box.clientHeight) {
            svg.style.width = 'auto';
            svg.style.height = '100%';
        }
    }

    function layout(frame) {
        frame.querySelectorAll('.para,.note,.subline').forEach(function (el) {
            const max = el.classList.contains('note') ? 46 : (el.classList.contains('subline') ? 42 : 66);

            fitBlock(el, max, 26, el.classList.contains('note') ? 1.42 : 1.6);
        });

        const head = frame.querySelector('.head');

        if (head) { fitBlock(head, 96, 40, 1.26); }

        const list = frame.querySelector('.list');

        if (list) { fitBlock(list, 52, 26, 1.46); }

        const chart = frame.querySelector('.chart-holder');

        if (chart) { fitChart(chart); }
    }

    // প্রতিটি ধাপে একটি করে লেখার গ্রুপ বেশি দেখানো হয়—ফ্রেম ক্লোন করে সারি বানায়
    function states(frame) {
        const groups = frame.querySelectorAll('[data-rv]');

        for (let k = 1; k < groups.length; k++) {
            const clone = frame.cloneNode(true);
            clone.removeAttribute('id');
            strip.appendChild(clone);
        }

        strip.querySelectorAll('.frame').forEach(function (copy, index) {
            copy.querySelectorAll('[data-rv]').forEach(function (el, i) {
                el.style.visibility = i <= index ? 'visible' : 'hidden';
            });
        });

        document.body.style.height = (1920 * Math.max(1, groups.length)) + 'px';
    }

    function build() {
        template.style.background = BG;
        layout(template);
        states(template);
    }

    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(build);
    } else {
        build();
    }
</script>
</body>
</html>
