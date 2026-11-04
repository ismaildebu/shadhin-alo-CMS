@php
    // নতুন ব্লক বানানোর সময় JS এই খাঁচাটাই ক্লোন করে; তাই মার্কআপ এক জায়গায় থাকে।
    $tplItem = new \App\Models\News(['title' => 'শিরোনাম', 'summary' => '', 'published_at' => now()]);
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ই-পেপার সাজান</h2>
    </x-slot>

    <div class="ad-page ep-page">

        @if(session('success'))
            <div class="ad-alert ad-alert-ok">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="ad-alert ad-alert-err">{{ $errors->first() }}</div>
        @endif

        <div class="ad-pagehead">
            <h1>{{ $page->edition_date->format('d F Y, l') }}</h1>
            @php [$stateLabel, $stateClass] = $page->state(); @endphp
            <span class="ad-badge {{ $stateClass }}">{{ $stateLabel }}</span>
        </div>

        <form id="ep-form" method="POST" action="{{ route('epaper.update', $page) }}">
            @csrf
            @method('PUT')

            <input type="hidden" name="layout" id="ep-layout"
                   value="{{ json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) }}">

            <div class="ep-bar">
                <label class="ep-status">
                    <span>অবস্থা</span>
                    <select name="status" id="ep-status">
                        @foreach(\App\Models\EpaperPage::STATUS_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected($page->status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <button type="button" class="ad-btn" data-act="add-column">+ কলাম</button>
                <button type="button" class="ad-btn" data-act="equal">সমান প্রস্থ</button>

                <button type="submit" class="ad-btn-solid">সেভ করুন</button>

                <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="ad-btn">প্রকাশিত পাতা</a>

                <span class="ep-hint">কলামের রেখা টেনে প্রস্থ বদলান, ব্লক টেনে জায়গা বদলান, ছবির নিচের গ্রিপ টেনে সাইজ।</span>
            </div>

            <div class="ep-wrap">

                <aside class="ep-pool">
                    <div class="ep-pool-head">
                        <h2>সংবাদ বাছুন</h2>
                        <input type="search" id="ep-search" placeholder="শিরোনাম দিয়ে খুঁজুন">
                    </div>

                    <div class="ep-pool-list" id="ep-pool-list" aria-live="polite">
                        <p class="ep-pool-note">প্রকাশিত সংবাদ লোড হচ্ছে…</p>
                    </div>

                    <button type="button" class="ad-btn ep-pool-more" id="ep-more" hidden>আরও সংবাদ</button>
                </aside>

                <div class="ep-stage"
                     id="ep-stage"
                     data-limits="{{ json_encode($limits) }}"
                     data-news-url="{{ $newsUrl }}"
                     data-block-tpl="#ep-block-tpl"
                     data-col-tpl="#ep-col-tpl">

                    @foreach($plan['columns'] as $index => $column)
                        @if($index > 0)
                            <div class="ep-divider" data-left="{{ $index - 1 }}"
                                 role="separator" aria-orientation="vertical" tabindex="0"
                                 title="টেনে প্রস্থ বদলান"></div>
                        @endif

                        <div class="ep-col {{ $column['blocks'] === [] ? 'is-empty' : '' }}"
                             data-width="{{ $column['width'] }}"
                             style="flex-grow:{{ $column['width'] }}">

                            <div class="ep-col-bar">
                                <span class="ep-col-name">কলাম {{ $index + 1 }}</span>
                                <button type="button" class="ep-col-del" data-act="del-column" title="এই কলামটি মোছা">✕</button>
                            </div>

                            <div class="ep-drop">
                                @foreach($column['blocks'] as $block)
                                    @if(isset($news[$block['news_id']]))
                                        @include('admin.epaper._block', ['item' => $news[$block['news_id']], 'block' => $block])
                                    @endif
                                @endforeach
                            </div>

                            <p class="ep-col-empty">এখানে খবর টানুন, না হয় খবরে ক্লিক করুন</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </form>

        <template id="ep-col-tpl">
            <div class="ep-col is-empty" data-width="25" style="flex-grow:25">
                <div class="ep-col-bar">
                    <span class="ep-col-name">কলাম</span>
                    <button type="button" class="ep-col-del" data-act="del-column" title="এই কলামটি মোছা">✕</button>
                </div>
                <div class="ep-drop"></div>
                <p class="ep-col-empty">এখানে খবর টানুন, না হয় খবরে ক্লিক করুন</p>
            </div>
        </template>

        <template id="ep-block-tpl">
            @include('admin.epaper._block', ['item' => $tplItem, 'block' => ['news_id' => 0, 'image' => 'auto', 'image_height' => null]])
        </template>
    </div>

    <script src="{{ asset('js/admin-epaper.js') }}?v={{ filemtime(public_path('js/admin-epaper.js')) }}"></script>
</x-app-layout>
