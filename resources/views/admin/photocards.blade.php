<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ফটোকার্ড কেন্দ্র</h2>
    </x-slot>

    <div class="ad-page">

        @if(session('success'))
            <div class="ad-alert ad-alert-ok">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="ad-alert ad-alert-err">{{ $errors->first() }}</div>
        @endif

        <div class="ad-pagehead">
            <h1>ফটোকার্ড কেন্দ্র</h1>
        </div>

        <div class="ad-stats">
            <div class="ad-stat">
                <span class="ad-stat-ico tone-blue"><svg class="ad-ico" viewBox="0 0 24 24"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span>
                <span><div class="ad-stat-value">{{ $total }}</div><div class="ad-stat-label">প্রকাশিত খবর</div></span>
            </div>
            <div class="ad-stat">
                <span class="ad-stat-ico tone-green"><svg class="ad-ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/></svg></span>
                <span><div class="ad-stat-value">{{ $withCard }}</div><div class="ad-stat-label">কার্ড আছে</div></span>
            </div>
            <div class="ad-stat">
                <span class="ad-stat-ico tone-amber"><svg class="ad-ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
                <span><div class="ad-stat-value">{{ max(0, $total - $withCard) }}</div><div class="ad-stat-label">কার্ড নেই</div></span>
            </div>
        </div>

        <form method="POST" action="{{ route('photocards.defaults') }}" id="pc-defaults" class="ad-form">
            @csrf
            @method('PUT')

            <div class="ad-panel" style="margin-bottom:20px;">
                <div class="ad-panel-head"><h2>ডিফল্ট ডিজাইন</h2></div>
                <div class="ad-panel-body">

                    <div class="ad-three ad-fgroup">
                        <div>
                            <label class="ad-label">ডিজাইন</label>
                            <select name="card_template" class="ad-field">
                                @foreach($templates as $key => $label)
                                    <option value="{{ $key }}" @selected($defaults['template'] === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="ad-label">মাপ</label>
                            <select name="card_size" class="ad-field">
                                <option value="landscape" @selected($defaults['size'] === 'landscape')>ল্যান্ডস্কেপ ১২০০×৬৩০</option>
                                <option value="square" @selected($defaults['size'] === 'square')>চৌকো ১০৮০×১০৮০</option>
                                <option value="portrait" @selected($defaults['size'] === 'portrait')>পোর্ট্রেট ১০৮০×১৩৫০</option>
                            </select>
                        </div>
                        <div>
                            <label class="ad-label">রং (ক্যাটাগরির রং না থাকলে)</label>
                            <input type="color" name="card_accent" value="{{ $defaults['accent'] }}" class="ad-color">
                        </div>
                    </div>

                    <div class="ad-fgroup">
                        <div class="ad-checks">
                            <label class="ad-check"><input type="checkbox" name="card_logo" value="1" @checked($defaults['logo'])> লোগো দেখাবে</label>
                            <label class="ad-check"><input type="checkbox" name="card_category" value="1" @checked($defaults['category'])> ক্যাটাগরি দেখাবে</label>
                            <label class="ad-check"><input type="checkbox" name="card_date" value="1" @checked($defaults['date'])> তারিখ দেখাবে</label>
                        </div>
                    </div>

                    <div class="ad-fgroup" style="max-width:420px;">
                        <label class="ad-label">নিচে ডানের লেখা (সাইটের ঠিকানা)</label>
                        <input type="text" name="card_footer" value="{{ $defaults['footer'] }}" class="ad-field" maxlength="80"
                               placeholder="{{ $footerFallback ?: 'www.example.com' }}">
                        <p class="ad-hint">ফাঁকা রাখলে .env এর APP_URL এর ডোমেইন বসে ({{ $footerFallback ?: 'নেই' }})।</p>
                    </div>

                    <button type="submit" class="ad-btn-solid">ডিফল্ট সংরক্ষণ করুন</button>

                </div>
            </div>
        </form>

        <div class="ad-panel" style="margin-bottom:20px;">
            <div class="ad-panel-head"><h2>একসাথে সব কার্ড তৈরি</h2></div>
            <div class="ad-panel-body">
                <p class="ad-note" style="margin-bottom:12px;">
                    কার্ড নেই এমন প্রকাশিত খবরগুলোর (একবারে সর্বোচ্চ ১০০টি, নতুন থেকে পুরোনো) কার্ড ওপরের ডিজাইন অনুযায়ী তৈরি ও সংরক্ষণ হবে।
                    কাজ চলার সময় এই পেজ খোলা রাখুন। ওপরের ফর্মের বর্তমান মান (সংরক্ষণ না করলেও) ব্যবহার হবে।
                </p>

                <button type="button" id="pc-run" class="ad-btn-solid">কার্ড নেই এমন সব খবরের কার্ড তৈরি করুন</button>

                <div class="pc-progress" style="margin-top:14px;"><div id="pc-bar"></div></div>
                <p class="ad-hint" id="pc-progress-text"></p>
                <div class="pc-log" id="pc-log"></div>
            </div>
        </div>

        <div class="ad-panel">
            <div class="ad-panel-head"><h2>সাম্প্রতিক প্রকাশিত খবর</h2></div>
            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                        <tr>
                            <th>খবর</th>
                            <th>কার্ড</th>
                            <th>কাজ</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($recent as $item)
                        <tr>
                            <td>
                                <div class="ad-news-title">{{ \Illuminate\Support\Str::limit($item->title, 90) }}</div>
                                <div class="ad-news-sub">{{ $item->category->name ?? '—' }} &middot; {{ $item->display_date->format('d M Y') }}</div>
                            </td>
                            <td data-label="কার্ড">
                                @if($item->card_url)
                                    <img src="{{ $item->card_url }}" alt="" class="pc-thumb" loading="lazy">
                                @else
                                    <span class="ad-badge st-draft">নেই</span>
                                @endif
                            </td>
                            <td>
                                <div class="ad-actions">
                                    <a href="{{ route('news.card', $item) }}" class="ad-act act-edit">কার্ড পেজ</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="ad-empty">এখনো কোনো প্রকাশিত খবর নেই।</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    
    <script src="{{ asset('js/admin-photocard.js') }}"></script>
    <script src="{{ asset('js/admin-photocard-more.js') }}"></script>
    <script>
        (function () {
            const queueUrl = @json($queueUrl);
            const form = document.getElementById('pc-defaults');
            const btn = document.getElementById('pc-run');
            const bar = document.getElementById('pc-bar');
            const text = document.getElementById('pc-progress-text');
            const log = document.getElementById('pc-log');
            const token = document.querySelector('meta[name="csrf-token"]').content;

            function options(item) {
                return {
                    template: form.elements.card_template.value,
                    size: form.elements.card_size.value,
                    logo: form.elements.card_logo.checked,
                    category: form.elements.card_category.checked,
                    date: form.elements.card_date.checked,
                    accent: item.color || form.elements.card_accent.value,
                    color2: '#123b68',
                    twoTone: false,
                    badge: '',
                    cta: '',
                    kicker: item.byline || '',
                    excerpt: true,
                    caption: true,
                    social: true
                };
            }

            function note(message) {
                log.textContent += message + '\n';
                log.scrollTop = log.scrollHeight;
            }

            btn.addEventListener('click', async function () {
                btn.disabled = true;
                log.textContent = '';
                bar.style.width = '0';
                text.textContent = 'তালিকা আনা হচ্ছে…';

                let data;

                try {
                    const res = await fetch(queueUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });

                    if (!res.ok) { throw new Error('HTTP ' + res.status); }

                    data = await res.json();
                } catch (e) {
                    text.textContent = 'তালিকা আনা যায়নি: ' + e.message;
                    btn.disabled = false;
                    return;
                }

                if (!data.items.length) {
                    text.textContent = 'সব প্রকাশিত খবরের কার্ড আগেই তৈরি আছে।';
                    btn.disabled = false;
                    return;
                }

                const footer = form.elements.card_footer.value.trim() || data.footer;
                const canvas = document.createElement('canvas');
                let done = 0;
                let failed = 0;

                for (let i = 0; i < data.items.length; i++) {
                    const item = data.items[i];

                    text.textContent = (i + 1) + ' / ' + data.items.length + ' তৈরি হচ্ছে…';

                    try {
                        await PhotoCard.render(canvas, {
                            title: item.title,
                            category: item.category,
                            date: item.date,
                            image: item.image,
                            logo: data.logo,
                            emblem: data.emblem,
                            siteName: data.siteName,
                            tagline: data.tagline,
                            footer: footer,
                            excerpt: item.excerpt,
                            caption: item.caption,
                            byline: item.byline,
                            social: data.social
                        }, options(item));

                        const blob = await PhotoCard.toBlob(canvas, 'image/jpeg', 0.92);
                        const fd = new FormData();

                        fd.append('card', blob, 'card.jpg');

                        const res = await fetch(item.storeUrl, {
                            method: 'POST',
                            body: fd,
                            credentials: 'same-origin',
                            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        });

                        if (!res.ok) { throw new Error('HTTP ' + res.status); }

                        done++;
                    } catch (e) {
                        failed++;
                        note('ব্যর্থ: #' + item.id + ' ' + item.title.slice(0, 40) + ' (' + e.message + ')');
                    }

                    bar.style.width = Math.round(((i + 1) / data.items.length) * 100) + '%';
                }

                text.textContent = 'শেষ: ' + done + 'টি তৈরি হয়েছে' + (failed ? ', ' + failed + 'টি ব্যর্থ' : '') + '। তালিকা হালনাগাদ করতে পেজ রিফ্রেশ করুন।';
                btn.disabled = false;
            });
        })();
    </script>
</x-app-layout>