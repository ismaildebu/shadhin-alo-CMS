@php
    $v = fn (string $key) => old($key, $s[$key] ?? '');
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">সাইট সেটিং</h2>
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
            <h1>সাইট সেটিং</h1>
            <a href="{{ route('home') }}" target="_blank" rel="noopener" class="ad-tab">সাইট দেখুন</a>
        </div>

        <form method="POST" action="{{ route('site-settings.update') }}" enctype="multipart/form-data" class="ad-form">
            @csrf
            @method('PUT')

            {{-- ===== পরিচিতি ও লোগো ===== --}}
            <div class="ad-panel" style="margin-bottom:20px;">
                <div class="ad-panel-head"><h2>পরিচিতি ও লোগো</h2></div>
                <div class="ad-panel-body">

                    <div class="ad-two ad-fgroup">
                        <div>
                            <label class="ad-label">সাইটের নাম <span class="ad-req">*</span></label>
                            <input type="text" name="site_name" value="{{ $v('site_name') }}" class="ad-field" required>
                        </div>
                        <div>
                            <label class="ad-label">স্লোগান</label>
                            <input type="text" name="site_tagline" value="{{ $v('site_tagline') }}" class="ad-field">
                            <p class="ad-hint">হেডারের উপরের পট্টি ও ফুটারে সাইটের নামের পাশে দেখায়।</p>
                        </div>
                    </div>

                    <div class="ad-two ad-fgroup">
                        <div>
                            <label class="ad-label">লোগো</label>
                            <img src="{{ \App\Support\Settings::logoUrl() }}" alt="" class="ad-logo-prev">
                            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="ad-file">
                            <p class="ad-hint">png, jpg বা webp; সর্বোচ্চ ১ MB। পটভূমি স্বচ্ছ png হলে ভালো। উচ্চতা প্রায় ৯০ পিক্সেলে দেখায়।</p>
                            @if(($s['logo'] ?? '') !== '')
                                <label class="ad-check-inline">
                                    <input type="checkbox" name="remove_logo" value="1"> আপলোড করা লোগো মুছে আগের ডিফল্টে ফিরুন
                                </label>
                            @endif
                        </div>

                        <div>
                            <label class="ad-label">ফেভিকন (ব্রাউজার ট্যাবের ছোট আইকন)</label>
                            @if(\App\Support\Settings::faviconUrl())
                                <img src="{{ \App\Support\Settings::faviconUrl() }}" alt="" class="ad-fav-prev">
                            @endif
                            <input type="file" name="favicon" accept="image/png,image/x-icon,image/webp,.ico" class="ad-file">
                            <p class="ad-hint">png বা ico; সর্বোচ্চ ৫১২ KB; চৌকো (যেমন ৬৪×৬৪) হলে ভালো।</p>
                            @if(($s['favicon'] ?? '') !== '')
                                <label class="ad-check-inline">
                                    <input type="checkbox" name="remove_favicon" value="1"> ফেভিকন মুছুন
                                </label>
                            @endif
                        </div>
                    </div>

                    <div class="ad-fgroup" style="max-width:320px;">
                        <label class="ad-label">হেডারের উপরের পট্টিতে তারিখ</label>
                        <select name="show_date" class="ad-field">
                            <option value="1" @selected($v('show_date') === '1')>দেখাবে</option>
                            <option value="0" @selected($v('show_date') === '0')>দেখাবে না</option>
                        </select>
                    </div>

                </div>
            </div>

            {{-- ===== ব্রেকিং নিউজ ===== --}}
            <div class="ad-panel" style="margin-bottom:20px;">
                <div class="ad-panel-head"><h2>ব্রেকিং নিউজ টিকার</h2></div>
                <div class="ad-panel-body">

                    <div class="ad-two ad-fgroup">
                        <div>
                            <label class="ad-label">টিকার</label>
                            <select name="ticker_show" class="ad-field">
                                <option value="1" @selected($v('ticker_show') === '1')>দেখাবে</option>
                                <option value="0" @selected($v('ticker_show') === '0')>দেখাবে না</option>
                            </select>
                        </div>
                        <div>
                            <label class="ad-label">লেবেলের লেখা <span class="ad-req">*</span></label>
                            <input type="text" name="ticker_label" value="{{ $v('ticker_label') }}" class="ad-field" maxlength="40" required>
                        </div>
                    </div>

                    <div class="ad-three ad-fgroup">
                        <div>
                            <label class="ad-label">কয়টি খবর (১–২০)</label>
                            <input type="number" name="ticker_count" min="1" max="20" value="{{ $v('ticker_count') }}" class="ad-field">
                        </div>
                        <div>
                            <label class="ad-label">গতি</label>
                            <select name="ticker_speed" class="ad-field">
                                <option value="slow" @selected($v('ticker_speed') === 'slow')>ধীর</option>
                                <option value="normal" @selected($v('ticker_speed') === 'normal')>মাঝারি</option>
                                <option value="fast" @selected($v('ticker_speed') === 'fast')>দ্রুত</option>
                            </select>
                        </div>
                        <div>
                            <label class="ad-label">কত ঘণ্টার পুরোনো খবর বাদ</label>
                            <input type="number" name="ticker_max_hours" min="0" max="720" value="{{ $v('ticker_max_hours') }}" class="ad-field">
                            <p class="ad-hint">০ মানে সীমা নেই।</p>
                        </div>
                    </div>

                    <p class="ad-hint" style="margin:0;">টিকারে সেই খবরগুলো আসে যেগুলোর সংবাদ ফর্মে "ব্রেকিং নিউজ" টিক দেওয়া এবং যা প্রকাশিত।</p>

                </div>
            </div>

            {{-- ===== ফুটার ===== --}}
            <div class="ad-panel">
                <div class="ad-panel-head"><h2>ফুটার</h2></div>
                <div class="ad-panel-body">

                    <div class="ad-fgroup">
                        <label class="ad-label">সাইটের পরিচিতি</label>
                        <textarea name="footer_about" rows="3" class="ad-field" maxlength="600">{{ $v('footer_about') }}</textarea>
                    </div>

                    <div class="ad-three ad-fgroup">
                        <div>
                            <label class="ad-label">ইমেইল</label>
                            <input type="text" name="footer_email" value="{{ $v('footer_email') }}" class="ad-field">
                        </div>
                        <div>
                            <label class="ad-label">ফোন</label>
                            <input type="text" name="footer_phone" value="{{ $v('footer_phone') }}" class="ad-field">
                        </div>
                        <div>
                            <label class="ad-label">ঠিকানা</label>
                            <input type="text" name="footer_address" value="{{ $v('footer_address') }}" class="ad-field">
                        </div>
                    </div>

                    <div class="ad-fgroup" style="max-width:320px;">
                        <label class="ad-label">ফুটারে কয়টি ক্যাটাগরির লিংক (০–২০)</label>
                        <input type="number" name="footer_categories" min="0" max="20" value="{{ $v('footer_categories') }}" class="ad-field">
                        <p class="ad-hint">মেনুর ক্রমে প্রথম কয়টি। ০ দিলে ক্যাটাগরির তালিকা আসবে না।</p>
                    </div>

                    <div class="ad-fgroup">
                        <label class="ad-label">অতিরিক্ত লিংক (যেমন আমাদের সম্পর্কে, গোপনীয়তা নীতি)</label>
                        <textarea name="footer_links" rows="4" class="ad-field ads-code-input"
                                  placeholder="আমাদের সম্পর্কে | /about&#10;যোগাযোগ | https://example.com/contact">{{ $v('footer_links') }}</textarea>
                        <p class="ad-hint">প্রতি লাইনে একটি: <code>লেবেল | লিংক</code>। লিংক <code>https://...</code> অথবা <code>/</code> দিয়ে শুরু। সর্বোচ্চ ১২টি।</p>
                    </div>

                    <div class="ad-two ad-fgroup">
                        <div>
                            <label class="ad-label">ফেসবুক পেজের লিংক</label>
                            <input type="text" name="social_facebook" value="{{ $v('social_facebook') }}" class="ad-field" placeholder="https://facebook.com/...">
                        </div>
                        <div>
                            <label class="ad-label">ইউটিউব চ্যানেলের লিংক</label>
                            <input type="text" name="social_youtube" value="{{ $v('social_youtube') }}" class="ad-field" placeholder="https://youtube.com/@...">
                        </div>
                    </div>

                    <div class="ad-two ad-fgroup">
                        <div>
                            <label class="ad-label">এক্স (টুইটার) এর লিংক</label>
                            <input type="text" name="social_x" value="{{ $v('social_x') }}" class="ad-field" placeholder="https://x.com/...">
                        </div>
                        <div>
                            <label class="ad-label">ইনস্টাগ্রামের লিংক</label>
                            <input type="text" name="social_instagram" value="{{ $v('social_instagram') }}" class="ad-field" placeholder="https://instagram.com/...">
                        </div>
                    </div>

                    <div class="ad-two ad-fgroup">
                        <div>
                            <label class="ad-label">কপিরাইটের লেখা</label>
                            <input type="text" name="footer_copyright" value="{{ $v('footer_copyright') }}" class="ad-field">
                        </div>
                        <div>
                            <label class="ad-label">"Developed by" এর নাম</label>
                            <input type="text" name="footer_credit" value="{{ $v('footer_credit') }}" class="ad-field">
                            <p class="ad-hint">ফাঁকা রাখলে এই লাইন আসবে না।</p>
                        </div>
                    </div>

                </div>
            </div>

            <div class="ad-sticky-save">
                <button type="submit" class="ad-btn-solid">সংরক্ষণ করুন</button>
                <span class="ad-hint" style="margin:0;">সংরক্ষণের পর সাইটে সঙ্গে সঙ্গে বদলে যাবে।</span>
            </div>
        </form>

    </div>
</x-app-layout>