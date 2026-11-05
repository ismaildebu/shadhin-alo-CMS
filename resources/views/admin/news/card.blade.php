@php
    $accent = $card['color'] ?: $defaults['accent'];
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ফটোকার্ড</h2>
    </x-slot>

    <div class="ad-page">

        <div class="ad-pagehead">
            <h1>ফটোকার্ড</h1>
            <div class="ad-tabs" style="margin-bottom:0;">
                <a href="{{ route('news.index') }}" class="ad-tab">← সংবাদ তালিকা</a>
                <a href="{{ route('news.edit', $news) }}" class="ad-tab">সংবাদ সম্পাদনা</a>
            </div>
        </div>

        <p class="ad-note">
            <b>{{ $news->title }}</b><br>
            ডিজাইন বেছে নিয়ে "শেয়ারের ছবি হিসেবে সংরক্ষণ" চাপুন। এরপর খবরের লিংক ফেসবুকে শেয়ার করলে এই কার্ডই দেখাবে।
        </p>

        <div class="pc-wrap">

            <div class="ad-panel">
                <div class="ad-panel-body">
                    <canvas id="card-canvas" class="pc-canvas"></canvas>

                    <div class="pc-btns" style="margin-top:14px;">
                        <button type="button" id="pc-dl-jpg" class="ad-btn-solid">ডাউনলোড (JPG)</button>
                        <button type="button" id="pc-dl-png" class="ad-btn-dark">ডাউনলোড (PNG)</button>
                        <button type="button" id="pc-save" class="ad-act act-ok" style="padding:9px 18px;">শেয়ারের ছবি হিসেবে সংরক্ষণ</button>
                    </div>

                    <p class="ad-hint" id="pc-status"></p>

                    <div id="pc-saved" @if(! $card['card']) hidden @endif style="margin-top:12px;">
                        <span class="ad-badge st-published">সংরক্ষিত কার্ড আছে</span>
                        <a href="{{ $card['card'] }}" id="pc-saved-link" target="_blank" rel="noopener" class="ad-act">দেখুন</a>
                        <button type="button" id="pc-copy" class="ad-act">লিংক কপি</button>
                        <button type="button" id="pc-del" class="ad-act act-del">মুছুন</button>
                    </div>
                </div>
            </div>

            <div>

                <div class="ad-panel" style="margin-bottom:20px;">
                    <div class="ad-panel-head"><h2>ডিজাইন</h2></div>
                    <div class="ad-panel-body ad-form">

                        <div class="ad-fgroup">
                            <label class="ad-label">কার্ডে যে শিরোনাম লেখা হবে</label>
                            <textarea id="pc-title" rows="3" class="ad-field">{{ $card['title'] }}</textarea>
                            <p class="ad-hint">লম্বা হলে ছোট করে লিখুন। এখানকার বদল খবরের শিরোনাম বদলায় না।</p>
                        </div>

                        <div class="ad-fgroup">
                            <label class="ad-label">ডিজাইন</label>
                            <select id="pc-template" class="ad-field">
                                @foreach($templates as $key => $label)
                                    <option value="{{ $key }}" @selected($defaults['template'] === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="ad-fgroup">
                            <label class="ad-label">মাপ</label>
                            <select id="pc-size" class="ad-field">
                                <option value="landscape" @selected($defaults['size'] === 'landscape')>ল্যান্ডস্কেপ ১২০০×৬৩০ (ফেসবুক লিংক)</option>
                                <option value="square" @selected($defaults['size'] === 'square')>চৌকো ১০৮০×১০৮০</option>
                                <option value="portrait" @selected($defaults['size'] === 'portrait')>পোর্ট্রেট ১০৮০×১৩৫০</option>
                            </select>
                        </div>

                        <div class="ad-fgroup">
                            <div class="ad-checks">
                                <label class="ad-check"><input type="checkbox" id="pc-logo" @checked($defaults['logo'])> লোগো</label>
                                <label class="ad-check"><input type="checkbox" id="pc-category" @checked($defaults['category'])> ক্যাটাগরি</label>
                                <label class="ad-check"><input type="checkbox" id="pc-date" @checked($defaults['date'])> তারিখ</label>
                            </div>
                        </div>

                        <div class="ad-fgroup">
                            <label class="ad-label">নিচে ডানের লেখা (সাইটের ঠিকানা)</label>
                            <input type="text" id="pc-footer" value="{{ $card['footer'] }}" class="ad-field" maxlength="80">
                        </div>

                        <div class="ad-fgroup">
                            <label class="ad-label">রং</label>
                            <input type="color" id="pc-accent" value="{{ $accent }}" class="ad-color">
                        </div>

                        <div class="ad-fgroup">
                            <label class="ad-check"><input type="checkbox" id="pc-excerpt-on" checked> সারাংশ দেখাবে</label>
                            <textarea id="pc-excerpt" rows="3" class="ad-field" style="margin-top:8px;">{{ $card['excerpt'] }}</textarea>
                        </div>

                    </div>
                </div>

            </div>

        </div>

    </div>

    <script>window.CARD = @json($card);</script>
    <script src="{{ asset('js/admin-photocard.js') }}?v={{ filemtime(public_path('js/admin-photocard.js')) }}"></script>
    <script>
        (function () {
            const d = window.CARD;
            const canvas = document.getElementById('card-canvas');
            const el = {
                title: document.getElementById('pc-title'),
                template: document.getElementById('pc-template'),
                size: document.getElementById('pc-size'),
                logo: document.getElementById('pc-logo'),
                category: document.getElementById('pc-category'),
                date: document.getElementById('pc-date'),
                footer: document.getElementById('pc-footer'),
                accent: document.getElementById('pc-accent'),
                excerptOn: document.getElementById('pc-excerpt-on'),
                excerpt: document.getElementById('pc-excerpt')
            };
            const status = document.getElementById('pc-status');
            const saved = document.getElementById('pc-saved');
            const savedLink = document.getElementById('pc-saved-link');
            const token = document.querySelector('meta[name="csrf-token"]').content;

            let busy = false;
            let again = false;

            function options() {
                return {
                    template: el.template.value,
                    size: el.size.value,
                    logo: el.logo.checked,
                    category: el.category.checked,
                    date: el.date.checked,
                    accent: el.accent.value,
                    excerpt: el.excerptOn.checked
                };
            }

            async function draw() {
                if (busy) { again = true; return; }

                busy = true;

                do {
                    again = false;

                    const data = Object.assign({}, d, {
                        title: el.title.value.trim() || d.title,
                        footer: el.footer.value.trim(),
                        excerpt: el.excerpt.value.trim()
                    });

                    await PhotoCard.render(canvas, data, options());
                } while (again);

                busy = false;
            }

            Object.keys(el).forEach(function (key) {
                el[key].addEventListener('input', draw);
                el[key].addEventListener('change', draw);
            });

            function download(type, ext) {
                canvas.toBlob(function (blob) {
                    const a = document.createElement('a');

                    a.href = URL.createObjectURL(blob);
                    a.download = 'photocard-' + d.id + '.' + ext;
                    document.body.appendChild(a);
                    a.click();
                    a.remove();

                    setTimeout(function () { URL.revokeObjectURL(a.href); }, 2000);
                }, type, 0.92);
            }

            document.getElementById('pc-dl-jpg').addEventListener('click', function () { download('image/jpeg', 'jpg'); });
            document.getElementById('pc-dl-png').addEventListener('click', function () { download('image/png', 'png'); });

            function showSaved(url) {
                savedLink.href = url;
                saved.hidden = false;
            }

            document.getElementById('pc-save').addEventListener('click', async function () {
                status.textContent = 'সংরক্ষণ হচ্ছে…';

                try {
                    const blob = await PhotoCard.toBlob(canvas, 'image/jpeg', 0.92);
                    const fd = new FormData();

                    fd.append('card', blob, 'card.jpg');

                    const res = await fetch(d.storeUrl, {
                        method: 'POST',
                        body: fd,
                        credentials: 'same-origin',
                        headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });

                    const json = await res.json().catch(function () { return {}; });

                    if (!res.ok) {
                        const first = json.errors && json.errors.card ? json.errors.card[0] : 'সংরক্ষণ ব্যর্থ হয়েছে (কোড ' + res.status + ')।';
                        throw new Error(first);
                    }

                    showSaved(json.url);
                    status.textContent = 'সংরক্ষণ হয়েছে। এখন খবরটি শেয়ার করলে এই কার্ড দেখাবে।';
                } catch (e) {
                    status.textContent = e.message;
                }
            });

            document.getElementById('pc-copy').addEventListener('click', function () {
                navigator.clipboard.writeText(location.origin + savedLink.getAttribute('href')).then(function () {
                    status.textContent = 'কার্ডের লিংক কপি হয়েছে।';
                });
            });

            document.getElementById('pc-del').addEventListener('click', async function () {
                if (!confirm('সংরক্ষিত কার্ডটি মুছে ফেলতে চান? খবরের শেয়ারে আগের মতো মূল ছবি দেখাবে।')) { return; }

                const res = await fetch(d.deleteUrl, {
                    method: 'POST',
                    body: new URLSearchParams({ _method: 'DELETE' }),
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (res.ok) {
                    saved.hidden = true;
                    status.textContent = 'সংরক্ষিত কার্ড মোছা হয়েছে।';
                } else {
                    status.textContent = 'মোছা যায়নি (কোড ' + res.status + ')।';
                }
            });

            draw();
        })();
    </script>
</x-app-layout>