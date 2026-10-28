
@component('layouts.app')
<link rel="stylesheet" href="{{ asset('advertisement-module/advertisement-designer.css') }}">

<div class="container-fluid py-3 ad-module" id="adDesigner">

    <header class="ad-head">
        <div>
            <h1>{{ $advertisement->exists ? 'বিজ্ঞাপন সম্পাদনা' : 'নতুন বিজ্ঞাপন ডিজাইন' }}</h1>
            <p>টেমপ্লেট বেছে নাও · মাউস দিয়ে সাজাও · PNG/JPG তৈরি করো</p>
        </div>
        <a class="ad-btn secondary" href="{{ route('advertisements.index') }}">← বিজ্ঞাপনের তালিকা</a>
    </header>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form id="adForm" method="post"
          action="{{ $advertisement->exists ? route('advertisements.update', $advertisement) : route('advertisements.store') }}">
        @csrf
        @if($advertisement->exists)
            @method('PUT')
        @endif

        <input type="hidden" name="design_data" id="designData"
               value="{{ old('design_data', is_string($advertisement->design_data ?? null) ? $advertisement->design_data : json_encode($advertisement->design_data ?? null)) }}">
        <input type="hidden" name="export_image" id="exportImage">

        <div class="ad-settings">
            <label>
                বিজ্ঞাপনের নাম
                <input name="title" required maxlength="150"
                       value="{{ old('title', $advertisement->title) }}"
                       placeholder="যেমন: ঈদ অফার">
            </label>

            <label>
                দেখানোর স্থান
                <select name="placement">
                    @foreach(['header'=>'হেডার','homepage'=>'হোমপেজ','sidebar'=>'সাইডবার','article'=>'সংবাদের পাতা'] as $value => $label)
                        <option value="{{ $value }}"
                            @selected(old('placement', $advertisement->placement ?? 'homepage') === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                ক্লিক করলে যে লিংকে যাবে
                <input type="url" name="destination_url"
                       value="{{ old('destination_url', $advertisement->destination_url) }}"
                       placeholder="https://example.com">
            </label>

            <label class="ad-check">
                <input type="checkbox" name="status" value="1"
                    @checked(old('status', $advertisement->status ?? true))>
                বিজ্ঞাপন সক্রিয়
            </label>
        </div>

        <div class="ad-workspace">

            <aside class="ad-tools">
                <h2>রেডিমেড টেমপ্লেট</h2>
                <p class="ad-small">একটি ডিজাইন বেছে নিয়ে নিজের মতো পরিবর্তন করো।</p>

                <div class="ad-template-tabs">
                    <button type="button" class="active" data-template-filter="all">সব</button>
                    <button type="button" data-template-filter="festival">উৎসব</button>
                    <button type="button" data-template-filter="sale">ছাড়</button>
                    <button type="button" data-template-filter="product">পণ্য</button>
                    <button type="button" data-template-filter="food">খাবার</button>
                </div>

                <div class="ad-template-gallery">
                    <button type="button" class="ad-template-card" data-template="eid" data-category="festival">
                        <span class="ad-template-preview eid-preview">
                            <b>ঈদ মোবারক</b><small>বিশেষ অফার</small>
                        </span>
                        <span>ঈদ ও উৎসব</span>
                    </button>

                    <button type="button" class="ad-template-card" data-template="sale" data-category="sale">
                        <span class="ad-template-preview sale-preview">
                            <b>BIG SALE</b><strong>৫০% OFF</strong>
                        </span>
                        <span>বিশেষ ছাড়</span>
                    </button>

                    <button type="button" class="ad-template-card" data-template="tech" data-category="product">
                        <span class="ad-template-preview tech-preview">
                            <b>নতুন প্রযুক্তি</b><small>পণ্য ও সেবা</small>
                        </span>
                        <span>প্রযুক্তি ও পণ্য</span>
                    </button>

                    <button type="button" class="ad-template-card" data-template="food" data-category="food">
                        <span class="ad-template-preview food-preview">
                            <b>স্পেশাল খাবার</b><small>আজই অর্ডার করুন</small>
                        </span>
                        <span>খাবার ও রেস্তোরাঁ</span>
                    </button>
                </div>

                <button type="button" id="clearTemplate" class="ad-clear-template">খালি ক্যানভাস</button>

                <hr>
                <h2>উপাদান যোগ করো</h2>
                <button type="button" data-add="text">＋ লেখা</button>
                <button type="button" data-add="rect">＋ রঙিন বাক্স</button>
                <button type="button" data-add="circle">＋ বৃত্ত</button>

                <label class="ad-upload">
                    ＋ ছবি যোগ করো
                    <input id="imageUpload" type="file" accept="image/*" hidden>
                </label>

                <hr>
                <label>
                    ক্যানভাসের মাপ
                    <select id="canvasSize">
                        <option value="1200x628">1200 × 628 — সোশ্যাল</option>
                        <option value="970x250">970 × 250 — ব্যানার</option>
                        <option value="300x250">300 × 250 — সাইডবার</option>
                        <option value="728x90">728 × 90 — লিডারবোর্ড</option>
                        <option value="1080x1080">1080 × 1080 — বর্গাকার</option>
                    </select>
                </label>

                <button type="button" id="deleteElement" class="danger">নির্বাচিত উপাদান মুছুন</button>
                <button type="button" id="exportPng">PNG প্রিভিউ তৈরি</button>
            </aside>

            <section class="ad-stage-wrap">
                <div class="ad-stage-note">
                    উপাদান নির্বাচন করে মাউস দিয়ে সরাও। কোণ বা পাশের গোল চিহ্ন ধরে আকার পরিবর্তন করো।
                </div>
                <div class="ad-stage">
                    <canvas id="adCanvas" width="1200" height="628"></canvas>
                </div>
                <div class="ad-zoom">
                    <button type="button" id="zoomOut">−</button>
                    <span id="zoomLabel">100%</span>
                    <button type="button" id="zoomIn">＋</button>
                    <button type="button" id="zoomFit">ক্যানভাস ফিট</button>
                </div>
            </section>

            <aside class="ad-properties">
                <h2>নির্বাচিত উপাদান</h2>

                <label>লেখা / বিষয়বস্তু
                    <textarea id="propText" rows="3"></textarea>
                </label>

                <label>রং
                    <input id="propColor" type="color" value="#ff6b35">
                </label>

                <label>ফন্টের আকার
                    <input id="propFont" type="number" min="8" max="160" value="48">
                </label>

                <label>প্রস্থ
                    <input id="propWidth" type="number" min="10" max="2000">
                </label>

                <label>উচ্চতা
                    <input id="propHeight" type="number" min="10" max="2000">
                </label>

                <label>ঘোরানো (ডিগ্রি)
                    <input id="propRotate" type="number" min="-180" max="180" value="0">
                </label>

                <p class="ad-help">উপাদান নির্বাচন না করলে সেটিংস নিষ্ক্রিয় থাকবে। পরিবর্তনগুলো ক্যানভাসে দেখা যাবে।</p>
            </aside>

        </div>

        <div class="ad-submit">
            <button class="ad-btn" type="submit">বিজ্ঞাপন সংরক্ষণ করো</button>
            <button class="ad-btn secondary" type="button" id="downloadJpg">JPG ডাউনলোড</button>
            <button class="ad-btn secondary" type="button" id="downloadPng">PNG ডাউনলোড</button>
        </div>
    </form>
</div>

<script>
window.advertisementInitial = @json($advertisement->design_data ?? null);
</script>
<script src="{{ asset('advertisement-module/advertisement-designer.js') }}"></script>
@endcomponent
