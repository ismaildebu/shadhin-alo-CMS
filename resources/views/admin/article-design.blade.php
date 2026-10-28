@php
    $o = $design['opt'];
    $old = old('widgets');
    $isOld = is_array($old);
    $hasOld = $errors->any();
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">সংবাদ পেজের ডিজাইন</h2>
    </x-slot>

    <div class="ad-page ad-narrow" style="max-width:62rem;">

        @if(session('success'))
            <div class="ad-alert ad-alert-ok">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="ad-alert ad-alert-err">
                <ul class="ad-errlist">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="ad-pagehead">
            <h1>সংবাদ পেজের ডিজাইন</h1>
            <a href="{{ route('home') }}" target="_blank" rel="noopener" class="ad-tab">সাইট দেখুন</a>
        </div>

        <p class="ad-note">
            কোনো খবর খুললে যে পেজ দেখা যায়, তার লেআউট, রং, লেখার আকার ও সাইডবার এখান থেকে ঠিক হবে।
            সংরক্ষণের পর যেকোনো খবর রিফ্রেশ করলেই নতুন ডিজাইন দেখা যাবে।
        </p>

        <form method="POST" action="{{ route('article-design.update') }}" class="ad-form">
            @csrf
            @method('PUT')

            {{-- ===== লেআউট ===== --}}
            <div class="ad-panel" style="margin-bottom:20px;">
                <div class="ad-panel-head"><h2>লেআউট</h2></div>
                <div class="ad-panel-body">
                    <div class="ad-two ad-fgroup">
                        <div>
                            <label class="ad-label">পেজের ধরন</label>
                            <select name="layout" class="ad-field">
                                @foreach(\App\Support\ArticleDesign::LAYOUTS as $key => $meta)
                                    <option value="{{ $key }}" @selected(old('layout', $o['layout']) === $key)>{{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="ad-label">সাইডবারের অবস্থান</label>
                            <select name="side" class="ad-field">
                                @foreach(\App\Support\ArticleDesign::SIDES as $key => $label)
                                    <option value="{{ $key }}" @selected(old('side', $o['side']) === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="ad-hint">"এক কলাম" বা সাইডবারে কিছু না রাখলে এটি কাজ করবে না।</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== রং ও লেখা ===== --}}
            <div class="ad-panel" style="margin-bottom:20px;">
                <div class="ad-panel-head"><h2>রং ও লেখা</h2></div>
                <div class="ad-panel-body">
                    <div class="ad-two ad-fgroup">
                        <div>
                            <label class="ad-label">মূল রং (শোল্ডার রেখা, উদ্ধৃতি, সাইডবারের হেডিং)</label>
                            <input type="color" name="accent" value="{{ old('accent', $o['accent']) }}">
                        </div>
                        <div>
                            <label class="ad-label">লিংকের রং</label>
                            <input type="color" name="link" value="{{ old('link', $o['link']) }}">
                        </div>
                    </div>

                    <div class="ad-two ad-fgroup">
                        <div>
                            <label class="ad-label">শিরোনাম ও লেখার ফন্ট</label>
                            <select name="font" class="ad-field">
                                @foreach(\App\Support\ArticleDesign::FONTS as $key => $meta)
                                    <option value="{{ $key }}" @selected(old('font', $o['font']) === $key)>{{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="ad-label">শিরোনামের আকার</label>
                            <select name="title_size" class="ad-field">
                                @foreach(\App\Support\ArticleDesign::TITLE_SIZES as $key => $meta)
                                    <option value="{{ $key }}" @selected(old('title_size', $o['title_size']) === $key)>{{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="ad-three ad-fgroup">
                        <div>
                            <label class="ad-label">লেখার আকার</label>
                            <select name="body_size" class="ad-field">
                                @foreach(\App\Support\ArticleDesign::BODY_SIZES as $key => $meta)
                                    <option value="{{ $key }}" @selected(old('body_size', $o['body_size']) === $key)>{{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="ad-label">লেখার প্রস্থ</label>
                            <select name="body_width" class="ad-field">
                                @foreach(\App\Support\ArticleDesign::BODY_WIDTHS as $key => $meta)
                                    <option value="{{ $key }}" @selected(old('body_width', $o['body_width']) === $key)>{{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="ad-label">লাইনের ফাঁক</label>
                            <select name="leading" class="ad-field">
                                @foreach(\App\Support\ArticleDesign::LEADINGS as $key => $meta)
                                    <option value="{{ $key }}" @selected(old('leading', $o['leading']) === $key)>{{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== ছবি ===== --}}
            <div class="ad-panel" style="margin-bottom:20px;">
                <div class="ad-panel-head"><h2>খবরের মূল ছবি</h2></div>
                <div class="ad-panel-body">
                    <div class="ad-two ad-fgroup">
                        <div>
                            <label class="ad-label">ছবির অনুপাত (সব খবরে একই সাইজ)</label>
                            <select name="image_ratio" class="ad-field">
                                @foreach(\App\Support\ArticleDesign::IMAGE_RATIOS as $key => $meta)
                                    <option value="{{ $key }}" @selected(old('image_ratio', $o['image_ratio']) === $key)>{{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="ad-label">ছবি বসানোর ধরন</label>
                            <select name="image_fit" class="ad-field">
                                @foreach(\App\Support\ArticleDesign::IMAGE_FITS as $key => $meta)
                                    <option value="{{ $key }}" @selected(old('image_fit', $o['image_fit']) === $key)>{{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <p class="ad-hint" style="margin:0;">আপলোড করা ফাইল বদলায় না; পেজে দেখানোর সময় ছবি নির্ধারিত বক্সে বসে। তাই যেকোনো সময় এটি বদলালে আগের সব খবরে প্রযোজ্য হবে।</p>
                </div>
            </div>

            {{-- ===== কোন অংশ দেখাবে ===== --}}
            <div class="ad-panel" style="margin-bottom:20px;">
                <div class="ad-panel-head"><h2>পেজের কোন অংশ দেখাবে</h2></div>
                <div class="ad-panel-body">
                    @foreach(\App\Support\ArticleDesign::TOGGLES as $key => $label)
                        <label class="ad-check-inline" style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="show_{{ $key }}" value="1"
                                   @checked($hasOld ? old('show_'.$key) !== null : $design['toggles'][$key])>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- ===== সাইডবার ===== --}}
            <div class="ad-panel" style="margin-bottom:20px;">
                <div class="ad-panel-head"><h2>সাইডবারের অংশ ও ক্রম</h2></div>
                <div class="ad-panel-body">
                    <p class="ad-hint" style="margin-top:0;">
                        <b>↑ ↓</b> বোতামে ক্রম বদলান, টিক তুলে লুকান। বিজ্ঞাপনের অংশে কোন বিজ্ঞাপন আসবে তা "বিজ্ঞাপন" পেজ থেকে ঠিক হয়।
                    </p>

                    <div class="ad-table-wrap">
                        <table class="ad-table">
                            <thead>
                                <tr>
                                    <th>ক্রম</th>
                                    <th>অংশ</th>
                                    <th class="ad-center">দেখাবে</th>
                                </tr>
                            </thead>
                            <tbody id="article-sidebar-rows">
                            @foreach($rows as $r)
                                @php
                                    $k = $r['key'];
                                    $row = $isOld ? ($old[$k] ?? []) : [];
                                    $show = $isOld ? isset($row['show']) : $r['visible'];
                                    $serial = $isOld ? ($row['serial'] ?? ($loop->iteration * 10)) : ($loop->iteration * 10);
                                @endphp
                                <tr data-row>
                                    <td data-label="ক্রম">
                                        <div class="ad-move">
                                            <span class="ad-pos" data-pos>{{ $loop->iteration }}</span>
                                            <button type="button" data-move="up" aria-label="ওপরে সরান">↑</button>
                                            <button type="button" data-move="down" aria-label="নিচে সরান">↓</button>
                                        </div>
                                        <input type="hidden" name="widgets[{{ $k }}][serial]" value="{{ $serial }}" data-serial>
                                    </td>
                                    <td>
                                        <span class="ad-tag-name">{{ $r['label'] }}</span>
                                        @if($r['note'])
                                            <div class="ad-news-sub">{{ $r['note'] }}</div>
                                        @endif
                                    </td>
                                    <td class="ad-center" data-label="দেখাবে">
                                        <input type="checkbox" name="widgets[{{ $k }}][show]" value="1" @checked($show)>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- ===== খবরের সংখ্যা ===== --}}
            <div class="ad-panel">
                <div class="ad-panel-head"><h2>কয়টি করে খবর</h2></div>
                <div class="ad-panel-body">
                    <div class="ad-three ad-fgroup">
                        @foreach(\App\Support\ArticleDesign::COUNTS as $key => $rule)
                            <div>
                                <label class="ad-label">{{ $rule['label'] }} ({{ $rule['min'] }}–{{ $rule['max'] }})</label>
                                <input type="number" name="{{ $key }}_count"
                                       min="{{ $rule['min'] }}" max="{{ $rule['max'] }}"
                                       value="{{ old($key.'_count', $design['counts'][$key]) }}" class="ad-field">
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="ad-sticky-save">
                <button type="submit" class="ad-btn-solid">সংরক্ষণ করুন</button>
                <span class="ad-hint" style="margin:0;">সংরক্ষণের পর সংবাদ পেজে সঙ্গে সঙ্গে বদলে যাবে।</span>
            </div>
        </form>

    </div>

    <script>
        (function () {
            const body = document.getElementById('article-sidebar-rows');

            function renumber() {
                let n = 10;

                body.querySelectorAll('tr[data-row]').forEach(function (tr, index) {
                    tr.querySelector('input[data-serial]').value = n;
                    tr.querySelector('[data-pos]').textContent = index + 1;
                    n += 10;
                });
            }

            body.addEventListener('click', function (event) {
                const btn = event.target.closest('button[data-move]');

                if (! btn) { return; }

                const tr = btn.closest('tr');

                if (btn.dataset.move === 'up' && tr.previousElementSibling) {
                    tr.parentNode.insertBefore(tr, tr.previousElementSibling);
                }

                if (btn.dataset.move === 'down' && tr.nextElementSibling) {
                    tr.parentNode.insertBefore(tr.nextElementSibling, tr);
                }

                renumber();
            });
        })();
    </script>
</x-app-layout>
