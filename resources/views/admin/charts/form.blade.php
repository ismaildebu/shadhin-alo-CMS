@php
    $data = is_array($chart->data) ? $chart->data : [];
    $oldType = old('type', $chart->type ?: 'grouped');
    $selectedType = array_key_exists($oldType, \App\Models\Chart::TYPES) ? $oldType : 'grouped';
    $oldStyle = old('style', $chart->style ?: 'classic');
    $selectedStyle = array_key_exists($oldStyle, \App\Models\Chart::STYLES) ? $oldStyle : 'classic';

    $fmt = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');

    $labelsValue = old('labels', implode('|', array_map('strval', $data['labels'] ?? [])));

    $seriesLines = [];

    foreach ((array) ($data['series'] ?? []) as $row) {
        $seriesLines[] = ($row['name'] ?? '').' | '.implode(', ', array_map($fmt, (array) ($row['values'] ?? [])));
    }

    $seriesValue = old('series_rows', implode("\n", $seriesLines));

    $singleLines = [];

    foreach ((array) ($data['labels'] ?? []) as $i => $label) {
        if (isset($data['values'][$i])) {
            $singleLines[] = $label.' | '.$fmt($data['values'][$i]);
        }
    }

    $rowsValue = old('rows', implode("\n", $singleLines));
    $published = $errors->any() ? old('is_published') !== null : (bool) $chart->is_published;
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $chart->exists ? 'গ্রাফ সম্পাদনা' : 'নতুন গ্রাফ' }}</h2>
    </x-slot>

    <link rel="stylesheet" href="{{ asset('css/article-visuals.css') }}">
    @include('partials.chart-scripts')

    <div class="ad-page">

        @if($errors->any())
            <div class="ad-alert ad-alert-err">{{ $errors->first() }}</div>
        @endif

        <div class="ad-pagehead">
            <h1>{{ $chart->exists ? 'গ্রাফ সম্পাদনা' : 'নতুন গ্রাফ' }}</h1>
            <a href="{{ route('charts.index') }}" class="ad-tab">সব গ্রাফ</a>
        </div>

        @if($chart->exists)
            <div class="ad-panel" style="margin-bottom:18px;">
                <div class="ad-panel-head"><h2>সংবাদে বসানোর শর্টকোড</h2></div>
                <div class="ad-panel-body">
                    <div style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;">
                        <input type="text" readonly value='[chart id="{{ $chart->id }}"]' class="ad-field" style="max-width:18rem;font-family:monospace;" id="chart-shortcode">
                        <button type="button" class="ad-btn-solid" id="chart-shortcode-copy">কপি করুন</button>
                        <span class="ad-hint" id="chart-shortcode-done" style="margin:0;"></span>
                    </div>
                    <p class="ad-hint">
                        শর্টকোডটি সংবাদের লেখার ভেতরে যেখানে গ্রাফ চান সেখানে পেস্ট করুন। একই লেখায় একাধিক শর্টকোড দিলে
                        সবগুলোই আলাদা গ্রাফ হিসেবে দেখাবে। গ্রাফটি <b>লুকানো</b> থাকলে শর্টকোড কোথাও দেখাবে না।
                    </p>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ $chart->exists ? route('charts.update', $chart) : route('charts.store') }}" class="ad-form">
            @csrf
            @if($chart->exists)
                @method('PUT')
            @endif

            <div class="ad-panel" style="margin-bottom:18px;">
                <div class="ad-panel-head"><h2>গ্রাফের তথ্য</h2></div>
                <div class="ad-panel-body">

                    <div class="ad-two ad-fgroup">
                        <div>
                            <label class="ad-label" for="chart-title">শিরোনাম</label>
                            <input type="text" id="chart-title" name="title" value="{{ old('title', $chart->title) }}" maxlength="150" class="ad-field" required placeholder="যেমন: বিভাগভিত্তিক সাক্ষরতার হার">
                        </div>
                        <div>
                            <label class="ad-label" for="chart-type">ধরন</label>
                            <select id="chart-type" name="type" class="ad-field">
                                @foreach(\App\Models\Chart::TYPES as $value => $label)
                                    <option value="{{ $value }}" @selected($selectedType === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="ad-two ad-fgroup">
                        <div>
                            <label class="ad-label" for="chart-style">স্টাইল</label>
                            <select id="chart-style" name="style" class="ad-field">
                                @foreach(\App\Models\Chart::STYLES as $value => $label)
                                    <option value="{{ $value }}" @selected($selectedStyle === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <p class="ad-hint" style="margin-top:1.9rem;">
                                একই তথ্য ছয়টি ডিজাইনে দেখানো যায় — ক্লাসিক, গ্রেডিয়েন্ট, আউটলাইন, রাউন্ডেড, ডার্ক ও মনোক্রোম।
                                স্টাইল বদলে আবার প্রিভিউ দেখুন।
                            </p>
                        </div>
                    </div>

                    <div class="ad-three ad-fgroup">
                        <div>
                            <label class="ad-label" for="chart-unit">একক (ঐচ্ছিক)</label>
                            <input type="text" id="chart-unit" name="unit" value="{{ old('unit', $chart->unit) }}" maxlength="20" class="ad-field" placeholder="যেমন: % বা কোটি">
                        </div>
                        <div>
                            <label class="ad-label" for="chart-source">সূত্র (ঐচ্ছিক)</label>
                            <input type="text" id="chart-source" name="source" value="{{ old('source', $chart->source) }}" maxlength="200" class="ad-field" placeholder="যেমন: বিবিএস ২০২৪">
                        </div>
                        <div>
                            <label class="ad-label" for="chart-serial">ক্রম</label>
                            <input type="number" id="chart-serial" name="serial" value="{{ old('serial', $chart->serial ?? 0) }}" min="0" max="99999" class="ad-field">
                            <p class="ad-hint">ছোট সংখ্যা আগে দেখাবে।</p>
                        </div>
                    </div>

                    <div class="ad-fgroup" id="chart-grouped-fields">
                        <label class="ad-label" for="chart-labels">লেবেল <span class="ad-hint">(একাধিক লেবেল | চিহ্ন দিয়ে আলাদা করুন)</span></label>
                        <input type="text" id="chart-labels" name="labels" value="{{ $labelsValue }}" class="ad-field" placeholder="২০২১|২০২২|২০২৩|২০২৪">

                        <label class="ad-label" for="chart-series" style="margin-top:12px;">সিরিজ <span class="ad-hint">(প্রতি লাইনে: সিরিজের নাম | মান, মান, ...)</span></label>
                        <textarea id="chart-series" name="series_rows" rows="5" class="ad-field ads-code-input" placeholder="Fig 1 | 10, 12, 9, 14&#10;Fig 2 | 15, 18, 13, 20">{{ $seriesValue }}</textarea>
                        <p class="ad-hint">প্রতিটি সিরিজে লেবেলের সমান সংখ্যক মান দিন। সর্বোচ্চ ৪টি সিরিজ ও ১২টি লেবেল।</p>
                    </div>

                    <div class="ad-fgroup" id="chart-single-fields">
                        <label class="ad-label" for="chart-rows">তথ্য <span class="ad-hint">(প্রতি লাইনে: লেবেল | সংখ্যা)</span></label>
                        <textarea id="chart-rows" name="rows" rows="7" class="ad-field ads-code-input" placeholder="ঢাকা | 78&#10;চট্টগ্রাম | 74&#10;রাজশাহী | 72">{{ $rowsValue }}</textarea>
                        <p class="ad-hint">কমপক্ষে ২টি ও সর্বোচ্চ ১২টি তথ্য দিন। বাংলা সংখ্যাও চলবে।</p>
                    </div>

                    <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-top:14px;">
                        <button type="button" id="chart-sample" class="ad-tab">নমুনা তথ্য বসান</button>
                        <button type="button" id="chart-preview-btn" class="ad-tab">প্রিভিউ দেখুন</button>
                    </div>

                    <div style="margin-top:16px;">
                        <label class="ad-check">
                            <input type="checkbox" name="is_published" value="1" @checked($published)> প্রকাশ করুন (/graphs পেজে ও শর্টকোডে দেখাবে)
                        </label>
                    </div>
                </div>
            </div>

            <div class="ad-panel" style="margin-bottom:18px;">
                <div class="ad-panel-head"><h2>প্রিভিউ</h2></div>
                <div class="ad-panel-body">
                    <div id="chart-preview-body">
                        @if($chart->exists && $data !== [])
                            {!! $chart->render() !!}
                        @else
                            <p class="ad-hint" style="margin:0;">তথ্য দিয়ে "প্রিভিউ দেখুন" চাপলে এখানে গ্রাফ আসবে।</p>
                        @endif
                    </div>
                    <div id="chart-preview-error" class="ad-alert ad-alert-err" style="display:none;margin-top:12px;"></div>
                </div>
            </div>

            <div class="ad-sticky-save">
                <button type="submit" class="ad-btn-solid">সংরক্ষণ করুন</button>
            </div>
        </form>

    </div>

    <input type="hidden" id="chart-token" value="{{ csrf_token() }}">

    <script>
        (function () {
            const $ = (id) => document.getElementById(id);
            const digits = { '০': '0', '১': '1', '২': '2', '৩': '3', '৪': '4', '৫': '5', '৬': '6', '৭': '7', '৮': '8', '৯': '9' };

            const samples = {
                grouped: {
                    labels: '২০২১|২০২২|২০২৩|২০২৪',
                    series: 'Fig 1 | 10, 12, 9, 14\nFig 2 | 15, 18, 13, 20'
                },
                single: 'ঢাকা | 78\nচট্টগ্রাম | 74\nরাজশাহী | 72\nখুলনা | 75',
                '3d': '২০১৯ | 8\n২০২০ | 14\n২০২১ | 21\n২০২২ | 28\n২০২৩ | 36\n২০২৪ | 44'
            };

            const typeSelect = $('chart-type');
            const groupedBox = $('chart-grouped-fields');
            const singleBox = $('chart-single-fields');
            const previewBox = $('chart-preview-body');
            const errorBox = $('chart-preview-error');

            function clean(text) {
                return text.replace(/["|\[\]=]/g, ' ').replace(/\s+/g, ' ').trim();
            }

            function number(text) {
                return text.trim().replace(/[০-৯]/g, (d) => digits[d]).replace(/[,\s]/g, '');
            }

            function showError(message) {
                errorBox.textContent = message;
                errorBox.style.display = message ? 'block' : 'none';
            }

            function refreshType() {
                const grouped = typeSelect.value === 'grouped';
                groupedBox.style.display = grouped ? '' : 'none';
                singleBox.style.display = grouped ? 'none' : '';
            }

            function buildSingle(type) {
                const lines = $('chart-rows').value.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);

                if (lines.length < 2) { throw new Error('কমপক্ষে দুইটি তথ্য দিন।'); }

                const labels = [];
                const values = [];

                lines.forEach(function (line, i) {
                    const at = line.indexOf('|');
                    const label = clean(at < 0 ? '' : line.slice(0, at));
                    const value = number(at < 0 ? '' : line.slice(at + 1));

                    if (label === '' || value === '' || isNaN(Number(value))) {
                        throw new Error((i + 1) + ' নম্বর লাইনে "লেবেল | সংখ্যা" দিন।');
                    }

                    labels.push(label);
                    values.push(value);
                });

                let code = '[chart type="' + type + '" style="' + $('chart-style').value + '" title="' + clean($('chart-title').value) + '" labels="' +
                    labels.join('|') + '" values="' + values.join('|') + '"';
                const unit = clean($('chart-unit').value);
                const source = clean($('chart-source').value);

                if (unit) { code += ' unit="' + unit + '"'; }
                if (source) { code += ' source="' + source + '"'; }

                return code + ']';
            }

            function buildGrouped() {
                const labels = $('chart-labels').value.split('|').map(clean).filter(Boolean);

                if (labels.length < 2) { throw new Error('কমপক্ষে দুইটি লেবেল দিন, মাঝে | চিহ্ন দিয়ে।'); }

                const lines = $('chart-series').value.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);

                if (lines.length === 0) { throw new Error('কমপক্ষে একটি সিরিজের তথ্য দিন।'); }

                const series = lines.map(function (line, i) {
                    const at = line.indexOf('|');
                    const name = clean(at < 0 ? '' : line.slice(0, at));

                    if (name === '') { throw new Error((i + 1) + ' নম্বর সিরিজে নাম দিন।'); }

                    const values = line.slice(at + 1).split(',').map(number);

                    if (values.some((value) => value === '' || isNaN(Number(value)))) {
                        throw new Error(name + ' সিরিজের সব মান সংখ্যা হতে হবে।');
                    }

                    if (values.length !== labels.length) {
                        throw new Error(name + ' সিরিজে ' + labels.length + 'টি মান দিন (লেবেলের সংখ্যার সমান)।');
                    }

                    return name + '=' + values.join(',');
                });

                let code = '[chart type="grouped" style="' + $('chart-style').value + '" title="' + clean($('chart-title').value) + '" labels="' +
                    labels.join('|') + '" series="' + series.join('|') + '"';
                const unit = clean($('chart-unit').value);
                const source = clean($('chart-source').value);

                if (unit) { code += ' unit="' + unit + '"'; }
                if (source) { code += ' source="' + source + '"'; }

                return code + ']';
            }

            typeSelect.addEventListener('change', refreshType);
            refreshType();

            $('chart-style').addEventListener('change', function () {
                if (previewBox.querySelector('.sa-visual')) {
                    $('chart-preview-btn').click();
                }
            });

            $('chart-sample').addEventListener('click', function () {
                if (typeSelect.value === 'grouped') {
                    $('chart-labels').value = samples.grouped.labels;
                    $('chart-series').value = samples.grouped.series;
                } else {
                    $('chart-rows').value = samples[typeSelect.value] || samples.single;
                }
            });

            $('chart-preview-btn').addEventListener('click', function () {
                showError('');
                let code;

                try {
                    code = typeSelect.value === 'grouped' ? buildGrouped() : buildSingle(typeSelect.value);
                } catch (error) {
                    showError(error.message);
                    return;
                }

                previewBox.innerHTML = '<p class="ad-hint" style="margin:0;">প্রিভিউ তৈরি হচ্ছে…</p>';

                fetch(@json(route('visual-builder.preview')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': $('chart-token').value
                    },
                    body: JSON.stringify({ shortcode: code })
                })
                    .then((response) => { if (!response.ok) { throw new Error('HTTP ' + response.status); } return response.json(); })
                    .then((data) => { previewBox.innerHTML = data.html; })
                    .catch(() => {
                        previewBox.innerHTML = '';
                        showError('প্রিভিউ আনা যায়নি। আবার চেষ্টা করুন।');
                    });
            });

            const copyButton = $('chart-shortcode-copy');

            if (copyButton) {
                copyButton.addEventListener('click', function () {
                    const input = $('chart-shortcode');
                    const done = () => { $('chart-shortcode-done').textContent = 'কপি হয়েছে।'; };

                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(input.value).then(done);
                    } else {
                        input.select();
                        document.execCommand('copy');
                        done();
                    }
                });
            }
        })();
    </script>
</x-app-layout>
