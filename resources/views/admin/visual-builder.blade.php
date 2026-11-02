<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ডেটা ভিজ্যুয়াল বিল্ডার</h2>
    </x-slot>

    <link rel="stylesheet" href="{{ asset('css/article-visuals.css') }}">
    @include('partials.chart-scripts')

    <div class="ad-page ad-narrow" style="max-width:62rem;">

        <div class="ad-pagehead">
            <h1>ডেটা ভিজ্যুয়াল বিল্ডার</h1>
            <a href="{{ route('charts.index') }}" class="ad-tab">সংরক্ষিত গ্রাফ</a>
        </div>

        <p class="ad-note">
            তথ্য দিলে চার্ট বা সংখ্যা কার্ডের <b>শর্টকোড</b> তৈরি হবে। শর্টকোডটি কপি করে সংবাদের লেখার যেখানে ছবির বদলে চার্ট চান সেখানে পেস্ট করুন।
            এখানকার প্রিভিউ পাবলিক পেজের মতোই দেখায়।
            একাধিক সিরিজের গ্রাফ এবং এক পেজে অনেকগুলো গ্রাফ রাখতে <a href="{{ route('charts.index') }}">গ্রাফ ও চার্ট</a> পেজ ব্যবহার করুন।
        </p>

        <div class="ad-panel" style="margin-bottom:20px;">
            <div class="ad-panel-head"><h2>তথ্য দিন</h2></div>
            <div class="ad-panel-body">

                <div class="ad-two ad-fgroup">
                    <div>
                        <label class="ad-label" for="vb-type">ধরন</label>
                        <select id="vb-type" class="ad-field">
                            <option value="bar">আনুভূমিক বার</option>
                            <option value="column">খাড়া কলাম</option>
                            <option value="line">লাইন (সময়ের সাথে পরিবর্তন)</option>
                            <option value="pie">পাই (ভাগ)</option>
                            <option value="donut">ডোনাট (ভাগ)</option>
                            <option value="3d">থ্রিডি অ্যারো (উত্থান)</option>
                            <option value="stats">সংখ্যা কার্ড (মূল তথ্য)</option>
                            <option value="map">বাংলাদেশের মানচিত্র (বিভাগ অনুযায়ী)</option>
                        </select>
                    </div>
                    <div>
                        <label class="ad-label" for="vb-title">শিরোনাম</label>
                        <input type="text" id="vb-title" class="ad-field" maxlength="120" placeholder="যেমন: বিভাগভিত্তিক সাক্ষরতার হার">
                    </div>
                </div>

                <div class="ad-two ad-fgroup">
                    <div id="vb-unit-wrap">
                        <label class="ad-label" for="vb-unit">একক (ঐচ্ছিক)</label>
                        <input type="text" id="vb-unit" class="ad-field" maxlength="12" placeholder="যেমন: % বা কোটি">
                    </div>
                    <div>
                        <label class="ad-label" for="vb-source">সূত্র (ঐচ্ছিক)</label>
                        <input type="text" id="vb-source" class="ad-field" maxlength="150" placeholder="যেমন: বিবিএস ২০২৪">
                    </div>
                </div>

                <div class="ad-two ad-fgroup">
                    <div>
                        <label class="ad-label" for="vb-style">স্টাইল</label>
                        <select id="vb-style" class="ad-field">
                            @foreach(\App\Models\Chart::STYLES as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <p class="ad-hint" style="margin-top:1.9rem;">একই তথ্য ছয়টি ডিজাইনে দেখানো যায়। স্টাইল বদলে আবার প্রিভিউ দেখুন।</p>
                    </div>
                </div>

                <div class="ad-fgroup" id="vb-map-wrap" style="display:none;">
                    <label class="ad-label">বিভাগ অনুযায়ী মান (ফাঁকা রাখলে সেই বিভাগ ধূসর দেখাবে)</label>
                    <div class="ad-three" id="vb-map-fields"></div>
                </div>

                <div class="ad-fgroup" id="vb-data-wrap">
                    <label class="ad-label" for="vb-data">তথ্য (প্রতি লাইনে একটি: <code>লেবেল | মান</code>)</label>
                    <textarea id="vb-data" rows="7" class="ad-field ads-code-input"></textarea>
                    <p class="ad-hint" id="vb-hint"></p>
                </div>

                <div style="display:flex; gap:.6rem; flex-wrap:wrap;">
                    <button type="button" id="vb-sample" class="ad-tab">নমুনা তথ্য বসান</button>
                    <button type="button" id="vb-preview" class="ad-btn-solid">প্রিভিউ দেখুন</button>
                </div>

                <div id="vb-error" class="ad-alert ad-alert-err" style="display:none; margin-top:14px;"></div>
            </div>
        </div>

        <div class="ad-panel" style="margin-bottom:20px;">
            <div class="ad-panel-head"><h2>শর্টকোড</h2></div>
            <div class="ad-panel-body">
                <textarea id="vb-code" rows="3" class="ad-field ads-code-input" readonly></textarea>
                <div style="margin-top:10px; display:flex; gap:.6rem; align-items:center;">
                    <button type="button" id="vb-copy" class="ad-btn-solid">কপি করুন</button>
                    <span class="ad-hint" id="vb-copied" style="margin:0;"></span>
                </div>
            </div>
        </div>

        <div class="ad-panel">
            <div class="ad-panel-head"><h2>প্রিভিউ</h2></div>
            <div class="ad-panel-body" id="vb-output">
                <p class="ad-hint" style="margin:0;">"প্রিভিউ দেখুন" চাপলে এখানে চার্ট আসবে।</p>
            </div>
        </div>

    </div>

    <input type="hidden" id="vb-token" value="{{ csrf_token() }}">

    <script>
        (function () {
            const $ = (id) => document.getElementById(id);
            const digits = { '০': '0', '১': '1', '২': '2', '৩': '3', '৪': '4', '৫': '5', '৬': '6', '৭': '7', '৮': '8', '৯': '9' };

            const samples = {
                bar: 'ঢাকা | 78\nচট্টগ্রাম | 74\nরাজশাহী | 72\nখুলনা | 75',
                column: '২০২১ | 12\n২০২২ | 18\n২০২৩ | 9\n২০২৪ | 21',
                line: '২০২০ | 5.2\n২০২১ | 6.9\n২০২২ | 7.1\n২০২৩ | 5.8\n২০২৪ | 6.4',
                pie: 'কৃষি | 40\nশিল্প | 30\nসেবা | 30',
                donut: 'কৃষি | 40\nশিল্প | 30\nসেবা | 30',
                '3d': '২০১৯ | 8\n২০২০ | 14\n২০২১ | 21\n২০২২ | 28\n২০২৩ | 36\n২০২৪ | 44',
                stats: 'মোট জনসংখ্যা | ১৭ কোটি\nসাক্ষরতার হার | ৭৬%\nশহরে বসবাস | ৪০%'
            };

            const hints = {
                bar: 'মান শুধু সংখ্যা (সর্বোচ্চ ১২টি লাইন)।',
                column: 'মান শুধু সংখ্যা (সর্বোচ্চ ১২টি লাইন)।',
                line: 'মান শুধু সংখ্যা; ঋণাত্মক হলে লিখুন -2.5। সময়ের ক্রমে দিন।',
                pie: 'মান শুধু সংখ্যা (সর্বোচ্চ ৮টি ভাগ)।',
                donut: 'মান শুধু সংখ্যা (সর্বোচ্চ ৮টি ভাগ)।',
                '3d': 'মান শুধু সংখ্যা; বাম থেকে ডানে বাড়লে তীরটি উত্থান বোঝায় (সর্বোচ্চ ১২টি লাইন)।',
                stats: 'মান লেখাও হতে পারে, যেমন "১৭ কোটি" (সর্বোচ্চ ৬টি কার্ড)।',
                map: 'প্রতিটি বিভাগের পাশে সংখ্যা দিন। রং গাঢ় মানে মান বেশি। মানচিত্র সরলীকৃত, সীমানা আনুমানিক।'
            };

            function clean(text) {
                return text.replace(/["|\[\]=]/g, ' ').replace(/\s+/g, ' ').trim();
            }

            function showError(message) {
                const box = $('vb-error');
                box.textContent = message;
                box.style.display = message ? 'block' : 'none';
            }

            const divisions = ['বরিশাল', 'চট্টগ্রাম', 'ঢাকা', 'খুলনা', 'ময়মনসিংহ', 'রাজশাহী', 'রংপুর', 'সিলেট'];
            const mapSamples = [60, 72, 78, 75, 70, 72, 66, 74];

            divisions.forEach(function (name) {
                const box = document.createElement('div');
                const label = document.createElement('label');
                const input = document.createElement('input');
                label.className = 'ad-label';
                label.textContent = name;
                input.type = 'text';
                input.className = 'ad-field';
                input.inputMode = 'decimal';
                input.dataset.name = name;
                box.appendChild(label);
                box.appendChild(input);
                $('vb-map-fields').appendChild(box);
            });

            function buildMap() {
                const pairs = [];

                document.querySelectorAll('#vb-map-fields input').forEach(function (input) {
                    const raw = input.value.trim().replace(/[০-৯]/g, (d) => digits[d]).replace(/,/g, '');

                    if (raw === '') { return; }
                    if (isNaN(Number(raw))) { throw new Error(input.dataset.name + ' এর মান সংখ্যা হতে হবে।'); }

                    pairs.push(input.dataset.name + '=' + raw);
                });

                if (!pairs.length) { throw new Error('অন্তত একটি বিভাগের মান দিন।'); }

                let code = '[map title="' + clean($('vb-title').value) + '" style="' + $('vb-style').value + '" data="' + pairs.join('|') + '"';
                const unit = clean($('vb-unit').value);
                const source = clean($('vb-source').value);

                if (unit) { code += ' unit="' + unit + '"'; }
                if (source) { code += ' source="' + source + '"'; }

                return code + ']';
            }

            function build() {
                const type = $('vb-type').value;

                if (type === 'map') { return buildMap(); }

                const lines = $('vb-data').value.split(/\r?\n/).map((l) => l.trim()).filter(Boolean);

                if (!lines.length) { throw new Error('তথ্য দিন।'); }

                const title = clean($('vb-title').value);
                const source = clean($('vb-source').value);
                let code;

                if (type === 'stats') {
                    const items = lines.map((line, i) => {
                        const parts = line.split('|');
                        const label = clean(parts[0] || '');
                        const value = clean(parts.slice(1).join(' '));
                        if (!label || !value) { throw new Error((i + 1) + ' নম্বর লাইনে "লেবেল | মান" দিন।'); }
                        return label + '=' + value;
                    });
                    code = '[stats title="' + title + '" style="' + $('vb-style').value + '" items="' + items.join('|') + '"';
                } else {
                    const labels = [];
                    const values = [];

                    lines.forEach((line, i) => {
                        const parts = line.split('|');
                        const label = clean(parts[0] || '');
                        const raw = (parts[1] || '').trim().replace(/[০-৯]/g, (d) => digits[d]).replace(/,/g, '');
                        if (!label || raw === '' || isNaN(Number(raw))) {
                            throw new Error((i + 1) + ' নম্বর লাইনে "লেবেল | সংখ্যা" দিন।');
                        }
                        labels.push(label);
                        values.push(raw);
                    });

                    code = '[chart type="' + type + '" style="' + $('vb-style').value + '" title="' + title + '" labels="' + labels.join('|') +
                        '" values="' + values.join('|') + '"';

                    const unit = clean($('vb-unit').value);
                    if (unit) { code += ' unit="' + unit + '"'; }
                }

                if (source) { code += ' source="' + source + '"'; }

                return code + ']';
            }

            function refreshUi() {
                const type = $('vb-type').value;
                $('vb-hint').textContent = hints[type];
                $('vb-unit-wrap').style.display = type === 'stats' ? 'none' : '';
                $('vb-data-wrap').style.display = type === 'map' ? 'none' : '';
                $('vb-map-wrap').style.display = type === 'map' ? '' : 'none';
            }

            $('vb-type').addEventListener('change', refreshUi);
            refreshUi();

            $('vb-style').addEventListener('change', function () {
                if ($('vb-code').value) {
                    $('vb-preview').click();
                }
            });

            $('vb-sample').addEventListener('click', function () {
                const type = $('vb-type').value;

                if (type === 'map') {
                    document.querySelectorAll('#vb-map-fields input').forEach(function (input, i) {
                        input.value = mapSamples[i];
                    });
                    return;
                }

                $('vb-data').value = samples[type];
            });

            $('vb-preview').addEventListener('click', function () {
                showError('');
                let code;

                try { code = build(); } catch (error) { showError(error.message); return; }

                $('vb-code').value = code;

                fetch(@json(route('visual-builder.preview')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': $('vb-token').value
                    },
                    body: JSON.stringify({ shortcode: code })
                })
                    .then((response) => { if (!response.ok) { throw new Error('HTTP ' + response.status); } return response.json(); })
                    .then((data) => { $('vb-output').innerHTML = data.html; })
                    .catch(() => showError('প্রিভিউ আনা যায়নি। আবার চেষ্টা করুন।'));
            });

            $('vb-copy').addEventListener('click', function () {
                const code = $('vb-code').value;

                if (!code) { showError('আগে "প্রিভিউ দেখুন" চাপুন।'); return; }

                const done = () => { $('vb-copied').textContent = 'কপি হয়েছে।'; };

                if (navigator.clipboard) {
                    navigator.clipboard.writeText(code).then(done);
                } else {
                    $('vb-code').select();
                    document.execCommand('copy');
                    done();
                }
            });
        })();
    </script>
</x-app-layout>
