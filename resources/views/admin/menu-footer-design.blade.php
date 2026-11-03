@php
    $o = $design['opt'];
    $v = fn (string $key) => old($key, $o[$key]);
    $menuPx = array_map(fn (array $size) => $size['px'], \App\Support\MenuFooterDesign::MENU_SIZES);
    $footerPx = array_map(fn (array $size) => $size['px'], \App\Support\MenuFooterDesign::FOOTER_SIZES);
    $siteName = \App\Support\Settings::get('site_name');
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">মেনু ও ফুটারের ডিজাইন</h2>
    </x-slot>

    <div class="ad-page ad-narrow" style="max-width:70rem;">

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
            <h1>মেনু ও ফুটারের ডিজাইন</h1>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="{{ route('menu.edit') }}" class="ad-tab">মেনু সাজান</a>
                <a href="{{ route('home') }}" target="_blank" rel="noopener" class="ad-tab">সাইট দেখুন</a>
            </div>
        </div>

        <p class="ad-note">
            মেনুর রং, লেখার আকার, অবস্থান ও স্টিকি এবং ফুটারের রং ও লেখার আকার এখান থেকে ঠিক করুন।
            ডান পাশের নমুনায় সঙ্গে সঙ্গে পরিবর্তন দেখা যাবে; সংরক্ষণ করলে পুরো সাইটে 적용 হবে।
        </p>

        <div class="mfd-wrap">

            <form id="mfd-form" method="POST" action="{{ route('menu-footer-design.update') }}" class="ad-form">
                @csrf
                @method('PUT')

                {{-- ===== মেনু ===== --}}
                <div class="ad-panel" style="margin-bottom:20px;">
                    <div class="ad-panel-head"><h2>মেনু (ওপরের নেভিগেশন)</h2></div>
                    <div class="ad-panel-body">

                        <div class="ad-three ad-fgroup">
                            <div>
                                <label class="ad-label">ব্যাকগ্রাউন্ড রং</label>
                                <input type="color" name="menu_bg" value="{{ $v('menu_bg') }}">
                            </div>
                            <div>
                                <label class="ad-label">লেখার রং</label>
                                <input type="color" name="menu_text" value="{{ $v('menu_text') }}">
                            </div>
                            <div>
                                <label class="ad-label">হোভার করলে ব্যাকগ্রাউন্ড</label>
                                <input type="color" name="menu_hover_bg" value="{{ $v('menu_hover_bg') }}">
                            </div>
                        </div>

                        <div class="ad-three ad-fgroup">
                            <div>
                                <label class="ad-label">লেখার আকার</label>
                                <select name="menu_size" class="ad-field">
                                    @foreach(\App\Support\MenuFooterDesign::MENU_SIZES as $key => $size)
                                        <option value="{{ $key }}" @selected($v('menu_size') === $key)>{{ $size['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="ad-label">আইটেমের অবস্থান</label>
                                <select name="menu_align" class="ad-field">
                                    @foreach(\App\Support\MenuFooterDesign::MENU_ALIGNS as $key => $label)
                                        <option value="{{ $key }}" @selected($v('menu_align') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="ad-label">স্ক্রল করলে</label>
                                <select name="menu_sticky" class="ad-field">
                                    <option value="1" @selected($v('menu_sticky') === '1')>উপরে আটকে থাকবে</option>
                                    <option value="0" @selected($v('menu_sticky') === '0')>সাইটের সাথে উঠে যাবে</option>
                                </select>
                            </div>
                        </div>

                        <p class="ad-hint" style="margin:0;">
                            আইটেমের নাম, ক্রম ও ড্রপডাউন বদলাতে <a href="{{ route('menu.edit') }}">মেনু সাজান</a> পেজে যান।
                        </p>

                    </div>
                </div>

                {{-- ===== ফুটার ===== --}}
                <div class="ad-panel">
                    <div class="ad-panel-head"><h2>ফুটার (পেজের নিচের অংশ)</h2></div>
                    <div class="ad-panel-body">

                        <div class="ad-three ad-fgroup">
                            <div>
                                <label class="ad-label">ব্যাকগ্রাউন্ড রং</label>
                                <input type="color" name="footer_bg" value="{{ $v('footer_bg') }}">
                            </div>
                            <div>
                                <label class="ad-label">লেখার রং</label>
                                <input type="color" name="footer_text" value="{{ $v('footer_text') }}">
                            </div>
                            <div>
                                <label class="ad-label">শিরোনামের রং</label>
                                <input type="color" name="footer_heading" value="{{ $v('footer_heading') }}">
                            </div>
                        </div>

                        <div class="ad-three ad-fgroup">
                            <div>
                                <label class="ad-label">অ্যাকসেন্ট রং</label>
                                <input type="color" name="footer_accent" value="{{ $v('footer_accent') }}">
                                <p class="ad-hint">মেনু ও ফুটারের উপরের রেখা, শিরোনামের নিচের দাগ এবং সোশ্যাল বাটনের হোভারে ব্যবহার হয়।</p>
                            </div>
                            <div>
                                <label class="ad-label">লিংকে হোভার করলে রং</label>
                                <input type="color" name="footer_hover" value="{{ $v('footer_hover') }}">
                            </div>
                            <div>
                                <label class="ad-label">লেখার আকার</label>
                                <select name="footer_size" class="ad-field">
                                    @foreach(\App\Support\MenuFooterDesign::FOOTER_SIZES as $key => $size)
                                        <option value="{{ $key }}" @selected($v('footer_size') === $key)>{{ $size['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <p class="ad-hint" style="margin:0;">
                            ফুটারের লেখা, লিংক, ঠিকানা ও কপিরাইট বদলাতে <a href="{{ route('site-settings.edit') }}">সাইট সেটিং</a> পেজে যান।
                        </p>

                    </div>
                </div>

                <div class="ad-sticky-save">
                    <button type="submit" class="ad-btn-solid">সংরক্ষণ করুন</button>
                    <span class="ad-hint" style="margin:0;">সংরক্ষণের পর সাইটের সব পেজে সঙ্গে সঙ্গে বদলে যাবে।</span>
                </div>
            </form>

            <aside class="mfd-side">
                <div class="ad-panel">
                    <div class="ad-panel-head"><h2>লাইভ প্রিভিউ</h2></div>
                    <div class="ad-panel-body">
                        <div class="mfd-preview" aria-hidden="true">
                            <nav class="mfd-menu" id="mfd-menu">
                                <span class="mfd-link is-current">হোম</span>
                                <span class="mfd-link">জাতীয়</span>
                                <span class="mfd-link">আন্তর্জাতিক</span>
                                <span class="mfd-link">খেলা</span>
                                <span class="mfd-link">বিনোদন</span>
                            </nav>

                            <footer class="mfd-footer" id="mfd-footer">
                                <div class="mfd-footer-title">{{ $siteName }}</div>
                                <p>নির্ভরযোগ্য বাংলা অনলাইন সংবাদমাধ্যম।</p>
                                <p>
                                    <span class="mfd-footer-link">আমাদের সম্পর্কে</span>
                                    <span class="mfd-footer-link">যোগাযোগ</span>
                                </p>
                                <span class="mfd-social">Facebook</span>
                            </footer>
                        </div>

                        <p class="ad-hint" id="mfd-sticky-note" style="margin:10px 0 0;"></p>
                    </div>
                </div>
            </aside>

        </div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('mfd-form');
            const menu = document.getElementById('mfd-menu');
            const footer = document.getElementById('mfd-footer');
            const note = document.getElementById('mfd-sticky-note');

            const menuPx = @json($menuPx);
            const footerPx = @json($footerPx);
            const aligns = { center: 'center', left: 'flex-start', right: 'flex-end' };

            function value(name) {
                const field = form.elements.namedItem(name);

                return field ? field.value : '';
            }

            function apply() {
                menu.style.background = value('menu_bg');
                menu.style.justifyContent = aligns[value('menu_align')] || 'center';
                menu.style.setProperty('--mfd-hover', value('menu_hover_bg'));
                menu.style.setProperty('--mfd-accent', value('footer_accent'));

                menu.querySelectorAll('.mfd-link').forEach(function (link) {
                    link.style.color = value('menu_text');
                    link.style.fontSize = (menuPx[value('menu_size')] || 15) + 'px';
                });

                const title = footer.querySelector('.mfd-footer-title');

                footer.style.background = value('footer_bg');
                footer.style.color = value('footer_text');
                footer.style.borderTopColor = value('footer_accent');
                footer.style.fontSize = (footerPx[value('footer_size')] || 15) + 'px';
                footer.style.setProperty('--mfd-link', value('footer_text'));
                footer.style.setProperty('--mfd-hover', value('footer_hover'));
                footer.style.setProperty('--mfd-accent', value('footer_accent'));

                title.style.color = value('footer_heading');
                title.style.borderBottomColor = value('footer_accent');

                note.textContent = value('menu_sticky') === '1'
                    ? 'মেনু স্ক্রল করলেও উপরে আটকে থাকবে।'
                    : 'মেনু স্ক্রল করলে সাইটের সাথে উঠে যাবে।';
            }

            form.addEventListener('input', apply);
            form.addEventListener('change', apply);
            apply();
        })();
    </script>
</x-app-layout>
