@php
    $old = old('rows');
    $isOld = is_array($old);
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">মেনু সাজান</h2>
    </x-slot>

    <div class="ad-page">

        @if(session('success'))
            <div class="ad-alert ad-alert-ok">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="ad-alert ad-alert-err">{{ $errors->first() }}</div>
        @endif

        <div class="ad-pagehead">
            <h1>মেনু সাজান</h1>
            <a href="{{ route('home') }}" target="_blank" rel="noopener" class="ad-tab">সাইট দেখুন</a>
        </div>

        <p class="ad-note">
            ওপর থেকে নিচের ক্রমে মেনুতে আসবে। <b>↑ ↓</b> বোতামে সরান। <b>অন্যান্য</b> বেছে নিলে আইটেমটি মূল মেনুর বদলে শেষের ড্রপডাউনে যাবে।
            ক্যাটাগরির নাম বদলাতে ক্যাটাগরি পেজে যান।
        </p>

        {{-- নিজের লিংক যোগ --}}
        <div class="ad-panel" style="margin-bottom:20px;">
            <div class="ad-panel-head"><h2>নিজের লিংক যোগ করুন</h2></div>
            <div class="ad-panel-body">
                <form method="POST" action="{{ route('menu.links.store') }}" class="ad-form">
                    @csrf
                    <div class="ad-linkrow">
                        <div>
                            <label class="ad-label">নাম</label>
                            <input type="text" name="label" value="{{ old('label') }}" class="ad-field" maxlength="60" placeholder="আমাদের সম্পর্কে" required>
                        </div>
                        <div>
                            <label class="ad-label">লিংক</label>
                            <input type="text" name="url" value="{{ old('url') }}" class="ad-field" placeholder="/about অথবা https://example.com" required>
                        </div>
                        <label class="ad-check" style="margin-bottom:6px;">
                            <input type="checkbox" name="new_tab" value="1" @checked(old('new_tab'))> নতুন ট্যাবে
                        </label>
                        <button type="submit" class="ad-btn-solid">যোগ করুন</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- মেনুর তালিকা --}}
        <form method="POST" action="{{ route('menu.update') }}" class="ad-form">
            @csrf
            @method('PUT')

            <div class="ad-panel" style="margin-bottom:20px;">
                <div class="ad-panel-body">
                    <div style="max-width:320px;">
                        <label class="ad-label">শেষের ড্রপডাউনের নাম</label>
                        <input type="text" name="more_label" value="{{ old('more_label', $moreLabel) }}" class="ad-field" maxlength="30" required>
                        <p class="ad-hint">যেসব আইটেম "অন্যান্য" এ রাখবেন সেগুলো এই নামের ড্রপডাউনে আসবে; একটিও না থাকলে ড্রপডাউন দেখাবে না।</p>
                    </div>
                </div>
            </div>

            <div class="ad-panel">
                <div class="ad-table-wrap">
                    <table class="ad-table">
                        <thead>
                            <tr>
                                <th>ক্রম</th>
                                <th>আইটেম</th>
                                <th class="ad-center">দেখাবে</th>
                                <th>কোথায়</th>
                                <th class="ad-center">নতুন ট্যাব</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="menu-rows">
                        @foreach($rows as $r)
                            @php
                                $k = $r['key'];
                                $o = $isOld ? ($old[$k] ?? []) : [];
                                $show = $isOld ? isset($o['show']) : $r['visible'];
                                $place = $isOld ? ($o['place'] ?? 'main') : ($r['more'] ? 'more' : 'main');
                                $newTab = $isOld ? isset($o['new_tab']) : $r['new_tab'];
                                $label = $isOld ? ($o['label'] ?? $r['label']) : $r['label'];
                                $url = $isOld ? ($o['url'] ?? $r['url']) : $r['url'];
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
                                    @if($isCat)
                                        <span class="ad-tag-name">{{ $r['label'] }}</span>
                                        <span class="ad-kind kind-cat">ক্যাটাগরি</span>
                                        <div class="ad-news-sub">
                                            @if($r['children'] > 0) উপ-ক্যাটাগরি: {{ $r['children'] }}টি @endif
                                            @if($r['inactive']) <span style="color:#b91c1c;">&middot; নিষ্ক্রিয় ক্যাটাগরি (মেনুতে আসবে না)</span> @endif
                                        </div>
                                    @else
                                        <div class="ad-stack">
                                            <input type="text" name="rows[{{ $k }}][label]" value="{{ $label }}" class="ad-field" maxlength="60" required>
                                            @if($r['type'] === 'link')
                                                <input type="text" name="rows[{{ $k }}][url]" value="{{ $url }}" class="ad-field" placeholder="/about অথবা https://...">
                                            @else
                                                <span class="ad-news-sub">
                                                    <span class="ad-kind kind-sys">ফিক্সড</span>
                                                    {{ \App\Models\MenuItem::TYPES[$r['type']] ?? '' }}র লিংক
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </td>

                                <td class="ad-center" data-label="দেখাবে">
                                    <input type="checkbox" name="rows[{{ $k }}][show]" value="1" @checked($show)>
                                </td>

                                <td data-label="কোথায়">
                                    <select name="rows[{{ $k }}][place]" class="ad-field">
                                        <option value="main" @selected($place === 'main')>মূল মেনু</option>
                                        <option value="more" @selected($place === 'more')>{{ $moreLabel }} ড্রপডাউন</option>
                                    </select>
                                </td>

                                <td class="ad-center" data-label="নতুন ট্যাব">
                                    @if($r['type'] === 'link')
                                        <input type="checkbox" name="rows[{{ $k }}][new_tab]" value="1" @checked($newTab)>
                                    @else
                                        <span class="ad-dash">—</span>
                                    @endif
                                </td>

                                <td>
                                    @if($r['type'] === 'link')
                                        <button type="submit" form="del-{{ $r['id'] }}" class="ad-act act-del"
                                                onclick="return confirm('লিংকটি মুছে ফেলতে চান? (এই পেজের অসংরক্ষিত পরিবর্তন হারাবে)')">মুছুন</button>
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
                <span class="ad-hint" style="margin:0;">সংরক্ষণের পর সাইটের মেনুতে সঙ্গে সঙ্গে বদলে যাবে।</span>
            </div>
        </form>

        {{-- মোছার ফর্মগুলো মূল ফর্মের বাইরে (HTML এ ফর্মের ভেতরে ফর্ম চলে না) --}}
        @foreach($rows as $r)
            @if($r['type'] === 'link')
                <form id="del-{{ $r['id'] }}" method="POST" action="{{ route('menu.links.destroy', $r['id']) }}" hidden>
                    @csrf
                    @method('DELETE')
                </form>
            @endif
        @endforeach

    </div>

    <script>
        (function () {
            const body = document.getElementById('menu-rows');

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