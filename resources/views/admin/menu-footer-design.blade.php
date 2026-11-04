@php
    $L = \App\Support\MenuFooterDesign::LABELS;
    $CHOICES = \App\Support\MenuFooterDesign::CHOICES;
    $o = $design['opt'];
    $v = fn (string $key) => old($key, $o[$key]);
    $siteName = \App\Support\Settings::get('site_name');

    /** The page renders every choice group the same way, so one map covers all of them. */
    $optionLabels = function (string $key) use ($CHOICES): array {
        if ($key === 'menu_sticky') {
            return ['1' => 'আটকে থাকবে', '0' => 'উঠে যাবে'];
        }

        return array_map(fn ($item) => is_array($item) ? $item['label'] : $item, $CHOICES[$key]);
    };

    $tabs = [
        'menu' => [
            'label' => 'মেনু বার',
            'colors' => ['menu_bg', 'menu_text', 'menu_hover_bg', 'menu_accent'],
            'choices' => ['menu_size', 'menu_align', 'menu_gap', 'menu_radius', 'menu_weight', 'menu_hover_style', 'menu_height', 'menu_top_line', 'menu_shadow', 'menu_sticky'],
            'note' => 'আইটেমের নাম, ক্রম ও ড্রপডাউন বদলাতে <a href="'.route('menu.edit').'">মেনু সাজান</a> পেজে যান।',
        ],
        'drop' => [
            'label' => 'ড্রপডাউন ও মেগা মেনু',
            'colors' => ['drop_bg', 'drop_text', 'drop_hover_bg'],
            'choices' => ['drop_radius', 'drop_width'],
            'note' => 'ড্রপডাউনের লেখার আকার মেনুর আকার থেকেই আসে। মোবাইলে তালিকাটি মেনু বারের ভেতরেই বসে, তাই সেখানে রং বারের গাঢ়তা থেকে নেয়।',
        ],
        'footer' => [
            'label' => 'ফুটার',
            'colors' => ['footer_bg', 'footer_text', 'footer_heading', 'footer_accent', 'footer_hover', 'footer_bottom_bg'],
            'choices' => ['footer_size', 'footer_pad', 'footer_gap', 'footer_top_line', 'social_shape'],
            'note' => 'ফুটারের লেখা, লিংক, ঠিকানা ও কপিরাইট বদলাতে <a href="'.route('site-settings.edit').'">সাইট সেটিং</a> পেজে যান।',
        ],
    ];

    // Value => pixels (or a shadow string) for the live preview; the page's own CSS does the rest.
    $px = function (string $key) use ($CHOICES): array {
        $out = [];

        foreach ($CHOICES[$key] as $val => $item) {
            $out[$val] = is_array($item) ? ($item['px'] ?? $item['css']) : $val;
        }

        return $out;
    };

    $numbers = [
        'menu_size' => $px('menu_size'),
        'menu_gap' => $px('menu_gap'),
        'menu_radius' => $px('menu_radius'),
        'menu_weight' => $px('menu_weight'),
        'menu_height' => $px('menu_height'),
        'menu_top_line' => $px('menu_top_line'),
        'menu_shadow' => $px('menu_shadow'),
        'drop_radius' => $px('drop_radius'),
        'drop_width' => $px('drop_width'),
        'footer_size' => $px('footer_size'),
        'footer_pad' => $px('footer_pad'),
        'footer_gap' => $px('footer_gap'),
        'footer_top_line' => $px('footer_top_line'),
        'social_shape' => $px('social_shape'),
    ];

    $presets = array_map(
        fn (array $p) => ['label' => $p['label'], 'values' => $p['values'], 'swatches' => array_values(array_intersect_key($p['values'], array_flip(['menu_bg', 'menu_accent', 'footer_bg', 'footer_accent'])))],
        \App\Support\MenuFooterDesign::PRESETS
    );
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">মেনু ও ফুটারের ডিজাইন</h2>
    </x-slot>

    <div class="ad-page ad-narrow" style="max-width:82rem;">

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
            মেনু, ড্রপডাউন ও ফুটারের রং, আকার, ফাঁক, কোণা ও ছায়া এখান থেকে ঠিক করুন।
            ডান পাশের নমুনায় সঙ্গে সঙ্গে পরিবর্তন দেখা যাবে; সংরক্ষণ করলে পুরো সাইটে প্রয়োগ হবে।
        </p>

        <div class="mfd-presets">
            <span class="ad-label">প্রস্তুত থিম</span>
            @foreach($presets as $key => $preset)
                <button type="button" class="mfd-preset" data-colors='@json($preset["values"])'>
                    <span class="mfd-preset-swatches" aria-hidden="true">
                        @foreach($preset['swatches'] as $swatch)
                            <i style="background:{{ $swatch }}"></i>
                        @endforeach
                    </span>
                    {{ $preset['label'] }}
                </button>
            @endforeach
        </div>

        <div class="mfd-wrap">

            <form id="mfd-form" method="POST" action="{{ route('menu-footer-design.update') }}" class="ad-form">
                @csrf
                @method('PUT')

                <div class="mfd-tabs" role="tablist">
                    @foreach($tabs as $key => $tab)
                        <button type="button" class="mfd-tab" role="tab" data-tab="{{ $key }}"
                                aria-controls="mfd-panel-{{ $key }}" @if($loop->first) aria-selected="true" @endif>
                            {{ $tab['label'] }}
                        </button>
                    @endforeach
                </div>

                @foreach($tabs as $key => $tab)
                    <fieldset class="ad-panel mfd-panel" id="mfd-panel-{{ $key }}" data-panel="{{ $key }}" @if(! $loop->first) hidden @endif>
                        <div class="ad-panel-body">

                            <div class="mfd-grid">
                                @foreach($tab['colors'] as $colorKey)
                                    <div class="mfd-field">
                                        <label class="ad-label" for="{{ $colorKey }}_hex">{{ $L[$colorKey] }}</label>
                                        <div class="mfd-color">
                                            <input class="ad-color" type="color" id="{{ $colorKey }}" name="{{ $colorKey }}"
                                                   value="{{ $v($colorKey) }}" data-hex="{{ $colorKey }}_hex"
                                                   aria-label="{{ $L[$colorKey] }}">
                                            <input class="ad-field mfd-hex" type="text" id="{{ $colorKey }}_hex"
                                                   value="{{ $v($colorKey) }}" data-color="{{ $colorKey }}"
                                                   maxlength="7" spellcheck="false" autocomplete="off">
                                        </div>
                                        @error($colorKey)<p class="ad-field-error">{{ $message }}</p>@enderror
                                    </div>
                                @endforeach
                            </div>

                            <div class="mfd-rows">
                                @foreach($tab['choices'] as $choiceKey)
                                    <div class="mfd-row">
                                        <span class="ad-label">{{ $L[$choiceKey] }}</span>
                                        <div class="mfd-seg" role="radiogroup" aria-label="{{ $L[$choiceKey] }}">
                                            @foreach($optionLabels($choiceKey) as $val => $label)
                                                <label class="mfd-seg-item">
                                                    <input type="radio" name="{{ $choiceKey }}" value="{{ $val }}"
                                                           @checked((string) $v($choiceKey) === (string) $val)>
                                                    <span>{{ $label }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @error($choiceKey)<p class="ad-field-error">{{ $message }}</p>@enderror
                                    </div>
                                @endforeach
                            </div>

                            <p class="ad-hint" style="margin:0;">{!! $tab['note'] !!}</p>

                        </div>
                    </fieldset>
                @endforeach
            </form>

            <aside class="mfd-side">
                <div class="ad-panel">
                    <div class="ad-panel-head">
                        <h2>লাইভ প্রিভিউ</h2>
                        <div class="mfd-devices" role="radiogroup" aria-label="নমুনার আকার">
                            <label class="mfd-seg-item">
                                <input type="radio" name="device" value="desktop" checked>
                                <span>ডেস্কটপ</span>
                            </label>
                            <label class="mfd-seg-item">
                                <input type="radio" name="device" value="mobile">
                                <span>মোবাইল</span>
                            </label>
                        </div>
                    </div>
                    <div class="ad-panel-body">
                        <div class="mfd-stage" id="mfd-stage" data-device="desktop" data-hover="fill" aria-hidden="true">

                            <div class="mfd-bar">
                                <span class="mfd-brand">{{ $siteName }}</span>
                                <nav class="mfd-nav">
                                    <span class="mfd-link is-current">হোম</span>
                                    <span class="mfd-link">জাতীয়</span>
                                    <span class="mfd-link">আন্তর্জাতিক</span>
                                    <span class="mfd-link has-caret">সারাদেশ</span>
                                    <span class="mfd-link">খেলা</span>
                                    <span class="mfd-link">বিনোদন</span>
                                </nav>
                            </div>

                            <div class="mfd-drop">
                                <span class="mfd-drop-item is-current">বরিশাল</span>
                                <span class="mfd-drop-item">ঢাকা</span>
                                <span class="mfd-drop-item">চট্টগ্রাম</span>
                                <span class="mfd-drop-item">খুলনা</span>
                            </div>

                            <div class="mfd-foot">
                                <div class="mfd-foot-grid">
                                    <div>
                                        <h4 class="mfd-foot-title">{{ $siteName }}</h4>
                                        <p>নির্ভরযোগ্য বাংলা অনলাইন সংবাদমাধ্যম।</p>
                                    </div>
                                    <div>
                                        <h4 class="mfd-foot-title">বিভাগ</h4>
                                        <ul class="mfd-foot-links">
                                            <li>জাতীয়</li>
                                            <li>রাজনীতি</li>
                                            <li>অর্থনীতি</li>
                                            <li>খেলা</li>
                                        </ul>
                                    </div>
                                    <div>
                                        <h4 class="mfd-foot-title">যোগাযোগ</h4>
                                        <p>info@shadhinalo.com</p>
                                        <span class="mfd-social">Facebook</span>
                                    </div>
                                </div>
                                <div class="mfd-foot-bottom">
                                    © {{ date('Y') }} {{ $siteName }} | সর্বস্বত্ব সংরক্ষিত
                                </div>
                            </div>
                        </div>

                        <p class="ad-hint" id="mfd-sticky-note" style="margin:10px 0 0;"></p>
                    </div>
                </div>
            </aside>

        </div>

        <div class="ad-sticky-save">
            <button type="submit" form="mfd-form" class="ad-btn-solid">সংরক্ষণ করুন</button>

            <form method="POST" action="{{ route('menu-footer-design.reset') }}"
                  onsubmit="return confirm('সব রং ও আকার আগের ডিফল্টে ফিরে যাবে। ঠিক আছে?');">
                @csrf
                @method('PUT')
                <button type="submit" class="ad-btn">ডিফল্টে ফিরুন</button>
            </form>

            <span class="ad-hint" style="margin:0;">সংরক্ষণের পর সাইটের সব পেজে সঙ্গে সঙ্গে বদলে যাবে।</span>
        </div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('mfd-form');
            const stage = document.getElementById('mfd-stage');
            const nav = stage.querySelector('.mfd-nav');
            const note = document.getElementById('mfd-sticky-note');

            const numbers = @json($numbers);
            const aligns = { center: 'center', left: 'flex-start', right: 'flex-end', between: 'space-between' };

            function value(name) {
                const field = form.elements.namedItem(name);

                return field ? field.value : '';
            }

            function num(name, fallback) {
                const map = numbers[name] || {};

                return Object.prototype.hasOwnProperty.call(map, value(name)) ? map[value(name)] : fallback;
            }

            function apply() {
                const set = (name, v) => stage.style.setProperty(name, v);

                set('--mfd-menu-bg', value('menu_bg'));
                set('--mfd-menu-text', value('menu_text'));
                set('--mfd-menu-hover', value('menu_hover_bg'));
                set('--mfd-accent', value('menu_accent'));
                set('--mfd-menu-px', num('menu_size', 15) + 'px');
                set('--mfd-gap', num('menu_gap', 2) + 'px');
                set('--mfd-radius', num('menu_radius', 9) + 'px');
                set('--mfd-weight', num('menu_weight', 600));
                set('--mfd-bar-pad', num('menu_height', 6) + 'px');
                set('--mfd-top-line', num('menu_top_line', 3) + 'px');
                set('--mfd-shadow', num('menu_shadow', 'none'));
                set('--mfd-align', aligns[value('menu_align')] || 'center');

                set('--mfd-drop-bg', value('drop_bg'));
                set('--mfd-drop-text', value('drop_text'));
                set('--mfd-drop-hover', value('drop_hover_bg'));
                set('--mfd-drop-radius', num('drop_radius', 12) + 'px');
                set('--mfd-drop-width', num('drop_width', 230) + 'px');

                set('--mfd-foot-bg', value('footer_bg'));
                set('--mfd-foot-text', value('footer_text'));
                set('--mfd-foot-heading', value('footer_heading'));
                set('--mfd-foot-accent', value('footer_accent'));
                set('--mfd-foot-hover', value('footer_hover'));
                set('--mfd-foot-bottom-bg', value('footer_bottom_bg'));
                set('--mfd-foot-px', num('footer_size', 15) + 'px');
                set('--mfd-foot-pad', num('footer_pad', 48) + 'px');
                set('--mfd-foot-gap', num('footer_gap', 8) + 'px');
                set('--mfd-foot-line', num('footer_top_line', 4) + 'px');
                set('--mfd-social-radius', num('social_shape', 999) + 'px');

                nav.dataset.hover = value('menu_hover_style');
                nav.dataset.sticky = value('menu_sticky');

                note.textContent = value('menu_sticky') === '1'
                    ? 'মেনু স্ক্রল করলেও উপরে আটকে থাকবে।'
                    : 'মেনু স্ক্রল করলে সাইটের সাথে উঠে যাবে।';
            }

            // হেক্স টেক্সট বক্স ও রঙের বক্স দুটিকে একসাথে রাখে।
            form.addEventListener('input', function (event) {
                const field = event.target;

                if (field.matches('input[type=color]')) {
                    const hex = form.querySelector('#' + CSS.escape(field.dataset.hex));

                    if (hex) hex.value = field.value;
                }

                if (field.matches('.mfd-hex')) {
                    const color = form.querySelector('#' + CSS.escape(field.dataset.color));
                    const typed = field.value.trim();

                    if (color && /^#[0-9a-f]{6}$/i.test(typed)) {
                        color.value = typed.toLowerCase();
                        field.value = color.value;
                    }
                }

                apply();
            });

            form.addEventListener('change', apply);

            document.querySelectorAll('.mfd-preset').forEach(function (button) {
                button.addEventListener('click', function () {
                    Object.entries(JSON.parse(button.dataset.colors)).forEach(function ([key, hex]) {
                        const field = form.elements.namedItem(key);

                        if (field && field.type === 'color') {
                            field.value = hex;
                            const hex2 = form.querySelector('#' + CSS.escape(field.dataset.hex));
                            if (hex2) hex2.value = hex;
                        }
                    });

                    apply();
                });
            });

            document.querySelectorAll('.mfd-tab').forEach(function (tab) {
                tab.addEventListener('click', function () { show(tab.dataset.tab); });
            });

            function show(key) {
                document.querySelectorAll('.mfd-tab').forEach(function (tab) {
                    tab.setAttribute('aria-selected', String(tab.dataset.tab === key));
                });
                document.querySelectorAll('.mfd-panel').forEach(function (panel) {
                    panel.hidden = panel.dataset.panel !== key;
                });
            }

            document.querySelectorAll('.mfd-devices input').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    stage.dataset.device = radio.value;
                });
            });

            // ত্রুটি থাকলে সেই ট্যাবটি খুলে দেখায়, নাহলে ভুল লুকিয়ে যেত।
            const firstError = document.querySelector('.mfd-panel .ad-field-error');

            if (firstError) {
                show(firstError.closest('.mfd-panel').dataset.panel);
            }

            apply();
        })();
    </script>
</x-app-layout>
