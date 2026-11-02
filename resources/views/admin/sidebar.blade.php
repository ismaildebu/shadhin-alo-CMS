@php
    $old = old('rows');
    $isOld = is_array($old);
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">সাইডবার সাজান</h2>
    </x-slot>

    <div class="ad-page">

        @if(session('success'))
            <div class="ad-alert ad-alert-ok">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="ad-alert ad-alert-err">{{ $errors->first() }}</div>
        @endif

        <div class="ad-pagehead">
            <h1>সাইডবার সাজান</h1>
            <div class="ad-tabs" style="margin-bottom:0;">
                <a href="{{ route('ads.index') }}" class="ad-tab">বিজ্ঞাপন</a>
                <a href="{{ route('home') }}" target="_blank" rel="noopener" class="ad-tab">সাইট দেখুন</a>
            </div>
        </div>

        <p class="ad-note">
            হোমপেজের ডানের সাইডবারের অংশগুলো ওপর থেকে নিচের ক্রমে আসবে। <b>↑ ↓</b> বোতামে সরান, টিক তুলে লুকান।
            "সর্বশেষ ও জনপ্রিয়" অংশের স্টাইল, রং (ট্যাব ও রেখার রং) এবং <b>স্ক্রল উচ্চতা</b> (ফাঁকা রাখলে স্টাইলের ডিফল্ট; <b>০</b> দিলে স্ক্রল বক্স থাকবে না) এখান থেকে ঠিক হয়।
            <b>অনলাইন ভোট</b> অংশের ছবির জায়গা (ওপরে/পাশে/ছবি ছাড়া) ও নিজের রংও এখান থেকে ঠিক করুন; প্রশ্ন, অপশন ও ছবি বদলাতে "অনলাইন ভোট" পেজে যান।
            বিজ্ঞাপনের অংশে কোন বিজ্ঞাপন আসবে তা "বিজ্ঞাপন" পেজ থেকে ঠিক হয়।
        </p>

        <form method="POST" action="{{ route('sidebar.update') }}" class="ad-form">
            @csrf
            @method('PUT')

            <div class="ad-panel">
                <div class="ad-table-wrap">
                    <table class="ad-table">
                        <thead>
                            <tr>
                                <th>ক্রম</th>
                                <th>অংশ</th>
                                <th class="ad-center">দেখাবে</th>
                                <th>স্টাইল</th>
                                <th>রং</th>
                                <th>স্ক্রল উচ্চতা</th>
                                <th>সংখ্যা</th>
                            </tr>
                        </thead>
                        <tbody id="sidebar-rows">
                        @foreach($rows as $r)
                            @php
                                $k = $r['key'];
                                $o = $isOld ? ($old[$k] ?? []) : [];
                                $show = $isOld ? isset($o['show']) : $r['visible'];
                                $limit = $isOld ? ($o['limit'] ?? $r['limit']) : $r['limit'];
                                $serial = $isOld ? ($o['serial'] ?? $r['serial']) : $r['serial'];
                                $layout = $isOld ? (string) ($o['layout'] ?? '') : (string) $r['layout'];
                                $accentOn = $isOld ? isset($o['accent_on']) : filled($r['accent']);
                                $accentVal = $isOld ? (string) ($o['accent'] ?? '#c40000') : ($r['accent'] ?: '#c40000');
                                $height = $isOld ? ($o['height'] ?? '') : $r['box_height'];
                            @endphp
                            <tr data-row>
                                <td data-label="ক্রম">
                                    <div class="ad-move">
                                        <span class="ad-pos" data-pos>{{ $loop->iteration }}</span>
                                        <button type="button" data-move="up" aria-label="ওপরে সরান">↑</button>
                                        <button type="button" data-move="down" aria-label="নিচে সরান">↓</button>
                                    </div>
                                    <input type="hidden" name="rows[{{ $k }}][serial]" value="{{ $serial }}" data-serial>
                                </td>

                                <td>
                                    <span class="ad-tag-name">{{ $r['label'] }}</span>
                                    @if($r['note'])
                                        <div class="ad-news-sub">{{ $r['note'] }}</div>
                                    @endif
                                    @if($k === 'facebook' && ! $facebookSet)
                                        <div class="ad-field-error">ফেসবুক লিংক এখনো দেওয়া নেই, তাই এটি টিক থাকলেও আসবে না (সাইট সেটিং থেকে দিন)।</div>
                                    @endif
                                </td>

                                <td class="ad-center" data-label="দেখাবে">
                                    <input type="checkbox" name="rows[{{ $k }}][show]" value="1" @checked($show)>
                                </td>

                                <td data-label="স্টাইল">
                                    @if($r['layouts'])
                                        <select name="rows[{{ $k }}][layout]" class="ad-field ad-field-md">
                                            @foreach($r['layouts'] as $key => $label)
                                                <option value="{{ $key }}" @selected($layout === $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="ad-dash">—</span>
                                    @endif
                                </td>

                                <td data-label="রং">
                                    @if($r['accent_meta'])
                                        <div class="ad-stack" style="min-width:0;">
                                            <label class="ad-check">
                                                <input type="checkbox" name="rows[{{ $k }}][accent_on]" value="1" @checked($accentOn)> নিজের রং
                                            </label>
                                            <input type="color" name="rows[{{ $k }}][accent]" value="{{ $accentVal }}" class="ad-color">
                                        </div>
                                    @else
                                        <span class="ad-dash">—</span>
                                    @endif
                                </td>

                                <td data-label="স্ক্রল উচ্চতা">
                                    @if($r['styled'])
                                        <input type="number" name="rows[{{ $k }}][height]" min="0" max="2000" step="10"
                                               value="{{ $height }}" placeholder="স্বয়ংক্রিয়"
                                               class="ad-field ad-field-sm" style="max-width:120px;">
                                        <div class="ad-news-sub">পিক্সেল; ০ = স্ক্রল নেই</div>
                                    @else
                                        <span class="ad-dash">—</span>
                                    @endif
                                </td>

                                <td data-label="সংখ্যা">
                                    @if($r['limit_meta'])
                                        <input type="number" name="rows[{{ $k }}][limit]"
                                               min="{{ $r['limit_meta']['min'] }}" max="{{ $r['limit_meta']['max'] }}"
                                               value="{{ $limit }}" class="ad-field ad-field-sm">
                                        <div class="ad-news-sub">{{ $r['limit_meta']['label'] }} ({{ $r['limit_meta']['min'] }}–{{ $r['limit_meta']['max'] }})</div>
                                    @else
                                        <span class="ad-dash">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="ad-sticky-save">
                <button type="submit" class="ad-btn-solid">সংরক্ষণ করুন</button>
                <span class="ad-hint" style="margin:0;">সংরক্ষণের পর হোমপেজে সঙ্গে সঙ্গে বদলে যাবে।</span>
            </div>
        </form>

    </div>

    <script>
        (function () {
            const body = document.getElementById('sidebar-rows');

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