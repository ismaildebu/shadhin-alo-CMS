@php
    $old = old('rows');
    $isOld = is_array($old);
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">হোমপেজ সাজান</h2>
    </x-slot>

    <div class="ad-page">

        @if(session('success'))
            <div class="ad-alert ad-alert-ok">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="ad-alert ad-alert-err">{{ $errors->first() }}</div>
        @endif

        <div class="ad-pagehead">
            <h1>হোমপেজ সাজান</h1>
            <a href="{{ route('home') }}" target="_blank" rel="noopener" class="ad-tab">সাইট দেখুন</a>
        </div>

        <p class="ad-note">
            ওপর থেকে নিচের ক্রমেই সেকশনগুলো হোমপেজে আসবে। <b>↑ ↓</b> বোতামে সারি সরান।
            <b>পুরো-চওড়া</b> টিক দিলে সেকশনটি সাইডবার ছাড়া পুরো প্রস্থে আসবে; পরপর পুরো-চওড়া নয় এমন সেকশনগুলো একসাথে সাইডবারের পাশে বসবে।
            <b>সংখ্যা</b> হলো ব্লকে কয়টি খবর (বা ভিডিও) দেখাবে; ক্যাটাগরিতে ফাঁকা রাখলে স্টাইলের ডিফল্ট সংখ্যা চলবে।
            <b>রং</b> হলো শিরোনাম ও রেখার রং, আর <b>ব্যাকগ্রাউন্ড</b> হলো পুরো ব্লকের পটভূমি (গাঢ় রং বেছে নিলে লেখা নিজে থেকে সাদা হবে)।
            যে ক্যাটাগরি বা সেকশনে দেখানোর মতো কিছু নেই, সেটি টিক থাকলেও আসবে না।
        </p>

        <form method="POST" action="{{ route('home-layout.update') }}" class="ad-form">
            @csrf
            @method('PUT')

            <div class="ad-panel">
                <div class="ad-table-wrap">
                    <table class="ad-table">
                        <thead>
                            <tr>
                                <th>ক্রম</th>
                                <th>সেকশন</th>
                                <th class="ad-center">দেখাবে</th>
                                <th class="ad-center">শিরোনাম</th>
                                <th>স্টাইল</th>
                                <th>রং</th>
                                <th>ব্যাকগ্রাউন্ড</th>
                                <th class="ad-center">পুরো-চওড়া</th>
                                <th>সংখ্যা</th>
                            </tr>
                        </thead>
                        <tbody id="home-rows">
                        @foreach($rows as $r)
                            @php
                                $k = $r['key'];
                                $o = $isOld ? ($old[$k] ?? []) : [];
                                $show = $isOld ? isset($o['show']) : $r['visible'];
                                $full = $isOld ? isset($o['full']) : $r['full'];
                                $layout = $isOld ? (string) ($o['layout'] ?? '') : (string) ($r['layout'] ?? '');
                                $color = $isOld ? (string) ($o['color'] ?? '#123b68') : ($r['color'] ?: '#123b68');
                                $bgOn = $isOld ? isset($o['bg_on']) : filled($r['bg']);
                                $bgVal = $isOld ? (string) ($o['bg'] ?? '#f1f5f9') : ($r['bg'] ?: '#f1f5f9');
                                $limit = $isOld ? ($o['limit'] ?? $r['limit']) : $r['limit'];
                                $serial = $isOld ? ($o['serial'] ?? $r['serial']) : $r['serial'];
                                $isCat = $r['kind'] === 'category';
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
                                    <span class="ad-kind {{ $isCat ? 'kind-cat' : 'kind-sys' }}">{{ $isCat ? 'ক্যাটাগরি' : 'সেকশন' }}</span>
                                    @if($isCat)
                                        <div class="ad-news-sub">
                                            প্রকাশিত খবর: {{ $r['news_count'] }}টি
                                            @if($r['inactive']) &middot; <span style="color:#b91c1c;">নিষ্ক্রিয় ক্যাটাগরি</span> @endif
                                        </div>
                                    @endif
                                </td>

                                <td class="ad-center" data-label="দেখাবে">
                                    <input type="checkbox" name="rows[{{ $k }}][show]" value="1" @checked($show)>
                                </td>
                                <td class="ad-center" data-label="শিরোনাম">
                                    @if($r['title_allowed'])
                                        <input type="hidden" name="rows[{{ $k }}][title_sent]" value="1">
                                        <input type="checkbox" name="rows[{{ $k }}][title]" value="1" @checked($isOld ? isset($o['title']) : $r['show_title'])>
                                    @else
                                        <span class="ad-dash">—</span>
                                    @endif
                                </td>

                                <td data-label="স্টাইল">
                                    @if($r['layouts'])
                                        <select name="rows[{{ $k }}][layout]" class="ad-field ad-field-md">
                                            @if($isCat)
                                                <option value="" @selected($layout === '')>স্বয়ংক্রিয়</option>
                                            @endif
                                            @foreach($r['layouts'] as $key => $label)
                                                <option value="{{ $key }}" @selected($layout === $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="ad-dash">—</span>
                                    @endif
                                </td>

                                <td data-label="রং">
                                    @if($r['color_allowed'])
                                        <input type="color" name="rows[{{ $k }}][color]" value="{{ $color }}" class="ad-color">
                                    @else
                                        <span class="ad-dash">—</span>
                                    @endif
                                </td>

                                <td data-label="ব্যাকগ্রাউন্ড">
                                    @if($r['bg_allowed'])
                                        <div class="ad-stack" style="min-width:0;">
                                            <label class="ad-check">
                                                <input type="checkbox" name="rows[{{ $k }}][bg_on]" value="1" @checked($bgOn)> চালু
                                            </label>
                                            <input type="color" name="rows[{{ $k }}][bg]" value="{{ $bgVal }}" class="ad-color">
                                        </div>
                                    @else
                                        <span class="ad-dash">—</span>
                                    @endif
                                </td>

                                <td class="ad-center" data-label="পুরো-চওড়া">
                                    @if($r['fixed_full'])
                                        <input type="checkbox" checked disabled title="এটি সবসময় পুরো-চওড়া">
                                        <input type="hidden" name="rows[{{ $k }}][full]" value="1">
                                    @else
                                        <input type="checkbox" name="rows[{{ $k }}][full]" value="1" @checked($full)>
                                    @endif
                                </td>

                                <td data-label="সংখ্যা">
                                    @if($r['limit_meta'])
                                        <input type="number" name="rows[{{ $k }}][limit]"
                                               min="{{ $r['limit_meta']['min'] }}" max="{{ $r['limit_meta']['max'] }}"
                                               value="{{ $limit }}" @if($isCat) placeholder="স্বয়ংক্রিয়" @endif
                                               class="ad-field ad-field-sm" style="max-width:110px;">
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
            const body = document.getElementById('home-rows');

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