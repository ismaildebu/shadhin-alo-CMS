<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">আলোচিত সার্চ</h2>
    </x-slot>

    @php
        $tab = request('tab', 'imported');
        $reasons = $checks->keyBy('trend_key');
        $digits = fn ($value) => \App\Support\BanglaTime::digits((string) $value);
    @endphp

    <div class="ad-page">

        @if(session('success'))
            <div class="ad-alert ad-alert-ok">{{ session('success') }}</div>
        @endif

        @if(session('verified'))
            <div class="ad-alert ad-alert-ok">উৎস যাচাই: {{ session('verified') }}</div>
        @endif

        @error('trends')
            <div class="ad-alert ad-alert-err">{{ $message }}</div>
        @enderror

        <div class="ad-pagehead">
            <h1>আলোচিত সার্চ থেকে সংবাদ</h1>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                <form method="POST" action="{{ route('trends.run') }}"
                      onsubmit="const b=this.querySelector('button'); b.disabled=true; b.querySelector('span').textContent='চলছে…';">
                    @csrf
                    <button type="submit" class="ad-tab"><span>এখনই যাচাই করুন</span></button>
                </form>
                <a href="{{ route('news.index', ['status' => 'draft']) }}" class="ad-tab">সব খসড়া দেখুন</a>
            </div>
        </div>

        <p class="ad-note">
            বাংলাদেশে এখন কোন শব্দগুলো সবচেয়ে বেশি খোঁজা হচ্ছে (Google Trends), সেগুলোর সাথে
            ফিড যে সংবাদ-লিংকগুলো দেয়— সেগুলো <b>নিজের পেজ থেকে</b> প্রকাশ-তারিখ যাচাই করে আনা হয়।
            শর্ত পূরণ না হলে সংবাদ আনা হয় না, বরং সেই শব্দের জন্য <b>তিনটি সম্ভাব্য বাংলা শিরোনাম</b>
            পরামর্শ হিসেবে দেওয়া হয় যাতে নিজেরা লেখা যায়। বিদেশি লেখা মেশিনে বাংলা হয়; অনুবাদ
            ব্যর্থ হলে খসড়াটি <b>“অনুবাদ প্রয়োজন”</b> ট্যাগ পায়।
            <b>বাদ পড়ার কারণ</b> প্রতিটি আইটেমের নিচে লেখা থাকে— সংখ্যা কম দেখালে কারণ দেখুন, ছাঁকনি ঢিলা করবেন না।
            আগের <a href="{{ route('news.index') }}">ফিড-সংগ্রহ</a> এর নিয়ম এটি বদলায় না।
        </p>

        <div class="ad-panel">
            <form method="POST" action="{{ route('trends.verify') }}">
                @csrf
                <div style="display:flex;gap:.6rem;align-items:flex-end;">
                    <div style="flex:1 1 320px;min-width:0;">
                        <label class="ad-label" for="trends-verify-url">উৎস যাচাই করুন</label>
                        <input class="ad-field" id="trends-verify-url" type="url" name="url" placeholder="https://www.ittefaq.com.bd/…" required>
                    </div>
                    <button type="submit" class="ad-btn ad-btn-dark">যাচাই</button>
                </div>
                <p class="ad-hint" style="margin-top:8px;">
                    একটি সংবাদ-পেজের ঠিকানা দিন: পেজ খোলে কি না, প্রতিবেদন আছে কি না, নিজের পেজে তারিখ পাওয়া যায় কি না— দেখাবে।
                    নতুন ডোমেইন <code>config/news_trends.php</code>-এর allow তালিকায় যোগ করার আগে ব্যবহার করুন।
                </p>
            </form>
        </div>

        <div class="ad-tabs">
            <a href="{{ route('trends.index', ['tab' => 'imported']) }}" class="ad-tab {{ $tab === 'imported' ? 'is-active' : '' }}">
                আমদানি হয়েছে <span class="ad-tab-count">({{ $digits($imports->count()) }})</span>
            </a>
            <a href="{{ route('trends.index', ['tab' => 'suggestions']) }}" class="ad-tab {{ $tab === 'suggestions' ? 'is-active' : '' }}">
                সম্ভাব্য শিরোনাম <span class="ad-tab-count">({{ $digits($suggestions->count()) }})</span>
            </a>
            <a href="{{ route('trends.index', ['tab' => 'forecasts']) }}" class="ad-tab {{ $tab === 'forecasts' ? 'is-active' : '' }}">
                আগামী ট্রেন্ড <span class="ad-tab-count">({{ $digits($forecasts->count()) }})</span>
            </a>
            <a href="{{ route('trends.index', ['tab' => 'checks']) }}" class="ad-tab {{ $tab === 'checks' ? 'is-active' : '' }}">
                বাদ পড়া ও কারণ <span class="ad-tab-count">({{ $digits($checks->count()) }})</span>
            </a>
        </div>

        @if($lastRun)
            <p class="ad-hint">শেষ যাচাই: {{ \App\Support\BanglaTime::date($lastRun) }}। ফিডে একবারে ১০টি ট্রেন্ড থাকে; প্রতি ঘণ্টায় স্বয়ংক্রিয়ভাবে একবার চলে।</p>
        @endif

        @if($tab === 'imported')
            <div class="ad-panel">
                <div class="ad-table-wrap">
                    <table class="ad-table">
                        <thead>
                        <tr>
                            <th>আলোচিত শব্দ</th>
                            <th>খসড়ার শিরোনাম</th>
                            <th>উৎস</th>
                            <th>ভাষা</th>
                            <th>প্রকাশ</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($imports as $event)
                            <tr>
                                <td data-label="আলোচিত শব্দ">
                                    {{ $event->query }}
                                    <div class="ad-hint">{{ $event->approx_traffic ? $digits($event->approx_traffic).' বার খোঁজা' : '' }}</div>
                                </td>
                                <td data-label="খসড়ার শিরোনাম">
                                    @if($event->news)
                                        <a href="{{ route('news.edit', $event->news) }}">{{ $event->title }}</a>
                                        @if($event->original_title && $event->original_title !== $event->title)
                                            <div class="ad-hint">মূল: {{ $event->original_title }}</div>
                                        @endif
                                        <div class="ad-hint">খসড়া #{{ $digits($event->news_id) }} · {{ $event->news->status }}</div>
                                    @else
                                        <span class="ad-hint">খসড়াটি মুছে ফেলা হয়েছে</span>
                                    @endif
                                </td>
                                <td data-label="উৎস">
                                    @if($event->source_link)
                                        <a href="{{ $event->source_link }}" target="_blank" rel="noopener nofollow">{{ $event->source_host }}</a>
                                    @else
                                        {{ $event->source_host }}
                                    @endif
                                </td>
                                <td data-label="ভাষা">
                                    {{ $event->language === 'bn' ? 'বাংলা' : 'বিদেশি' }}
                                    @if($event->translated)
                                        <div class="ad-hint">অনুবাদ হয়েছে</div>
                                    @elseif($event->translation_note !== '')
                                        <div class="ad-hint">{{ $event->translation_note }}</div>
                                    @endif
                                </td>
                                <td data-label="প্রকাশ">{{ $event->occurred_at ? \App\Support\BanglaTime::date($event->occurred_at) : '—' }}</td>
                                <td data-label="">
                                    <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                                        <form method="POST" action="{{ route('trends.update', $event) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="status" value="done">
                                            <button type="submit" class="ad-tab">দেখেছি</button>
                                        </form>
                                        <form method="POST" action="{{ route('trends.destroy', $event) }}"
                                              onsubmit="return confirm('সারিটি মুছে ফেলবেন?{{ $event->news ? ' খসড়া #'.$digits($event->news_id).' থাকবে— খসড়া মুছতে হলে সংবাদ তালিকায় যান।' : '' }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ad-act act-del">মুছুন</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="ad-empty">এখনো কিছু আমদানি হয়নি। উপরের “এখনই যাচাই করুন” চাপুন।</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if($tab === 'suggestions')
            <div class="ad-panel">
                <div class="ad-table-wrap">
                    <table class="ad-table">
                        <thead>
                        <tr>
                            <th>আলোচিত শব্দ</th>
                            <th>কেন সংবাদ আনা যায়নি</th>
                            <th>সম্ভাব্য শিরোনাম</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($suggestions as $event)
                            @php $check = $reasons->get($event->trend_key); @endphp
                            <tr>
                                <td data-label="আলোচিত শব্দ">
                                    {{ $event->query }}
                                    <div class="ad-hint">{{ $event->approx_traffic ? $digits($event->approx_traffic).' বার খোঁজা' : '' }}
                                        @if($event->source_host) · {{ $event->source_host }} @endif
                                    </div>
                                </td>
                                <td data-label="কারণ">{{ $check->reason ?? '—' }}</td>
                                <td data-label="সম্ভাব্য শিরোনাম">
                                    <ol style="margin:0;padding-left:1.2rem;">
                                        @foreach($event->headlineList() as $headline)
                                            <li>{{ $headline }}</li>
                                        @endforeach
                                    </ol>
                                    @if(! $event->translated && $event->translation_note !== '')
                                        <div class="ad-hint">অনুবাদ বাকি: {{ $event->translation_note }}</div>
                                    @endif
                                </td>
                                <td data-label="">
                                    <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                                        <a href="{{ route('news.create') }}" class="ad-tab">লেখা শুরু করুন</a>
                                        @if($event->source_link)
                                            <a href="{{ $event->source_link }}" target="_blank" rel="noopener nofollow" class="ad-tab">উৎস দেখুন</a>
                                        @endif
                                        <form method="POST" action="{{ route('trends.update', $event) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="status" value="hidden">
                                            <button type="submit" class="ad-tab">লুকান</button>
                                        </form>
                                        <form method="POST" action="{{ route('trends.destroy', $event) }}"
                                              onsubmit="return confirm('সারিটি মুছে ফেলবেন? শব্দটি ট্রেন্ডে থাকলে পরের ঘণ্টায় নতুন করে আসতে পারে।')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ad-act act-del">মুছুন</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="ad-empty">কোনো পরামর্শ জমা নেই।</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if($tab === 'forecasts')
            <div class="ad-panel">
                <div class="ad-table-wrap">
                    <table class="ad-table">
                        <thead>
                        <tr>
                            <th>হতে পারে</th>
                            <th>কীভাবে গণনা হয়েছে</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($forecasts as $event)
                            @php $headlines = $event->headlineList(); @endphp
                            <tr>
                                <td data-label="হতে পারে">{{ $event->title }}</td>
                                <td data-label="কীভাবে গণনা হয়েছে">
                                    {{ $headlines[0] ?? '—' }}
                                    @if($event->source_link)
                                        <div class="ad-hint"><a href="{{ $event->source_link }}">সেই সংবাদটি দেখুন</a></div>
                                    @endif
                                </td>
                                <td data-label="">
                                    <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                                        <form method="POST" action="{{ route('trends.update', $event) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="status" value="hidden">
                                            <button type="submit" class="ad-tab">লুকান</button>
                                        </form>
                                        <form method="POST" action="{{ route('trends.destroy', $event) }}"
                                              onsubmit="return confirm('সারিটি মুছে ফেলবেন? পরের চালু শর্ত মিললে আবার গণনা হতে পারে।')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ad-act act-del">মুছুন</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="ad-empty">পূর্বাভাসের তালিকা খালি — প্রথম চাল শেষ হলে ভরবে।</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if($tab === 'checks')
            <div class="ad-panel">
                <div class="ad-table-wrap">
                    <table class="ad-table">
                        <thead>
                        <tr>
                            <th>সময়</th>
                            <th>আলোচিত শব্দ</th>
                            <th>খোঁজ</th>
                            <th>অবস্থা</th>
                            <th>উৎস</th>
                            <th>কারণ</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($checks as $check)
                            <tr>
                                <td data-label="সময়">{{ \App\Support\BanglaTime::date($check->created_at) }}</td>
                                <td data-label="শব্দ">{{ $check->query }}</td>
                                <td data-label="খোঁজ">{{ $check->approx_traffic ?: '—' }}</td>
                                <td data-label="অবস্থা">
                                    @switch($check->verdict)
                                        @case('imported') <span style="color:#15803d;font-weight:600;">যোগ হয়েছে</span> @break
                                        @case('suggested') <span style="color:#b45309;font-weight:600;">পরামর্শ</span> @break
                                        @case('duplicate') <span class="ad-hint">আগেই আছে</span> @break
                                        @default <span class="ad-hint">বাদ</span>
                                    @endswitch
                                </td>
                                <td data-label="উৎস">{{ $check->candidate_host ?: '—' }}</td>
                                <td data-label="কারণ">
                                    {{ $check->reason }}
                                    @if($check->candidate_link)
                                        <div class="ad-hint"><a href="{{ $check->candidate_link }}" target="_blank" rel="noopener nofollow">যে পেজটি দেখা হয়েছে</a></div>
                                    @endif
                                </td>
                                <td data-label="">
                                    <form method="POST" action="{{ route('trends.checkDestroy', $check) }}"
                                          onsubmit="return confirm('হিসাবের সারিটি মুছে ফেলবেন? মুছলে এই শব্দের “আগেই আছে” ছাঁকনি ও পুনরাবৃত্তি-গণনা কমে যাবে।')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ad-act act-del">মুছুন</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="ad-empty">এখনো কোনো যাচাই জমা নেই।</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <p class="ad-note">
            <b>কনফিগ:</b> চাল-সীমা {{ $digits($config['collect']['max_per_run'] ?? 10) }}টি খসড়া,
            তার মধ্যে বিদেশি সর্বোচ্চ {{ $digits($config['collect']['max_foreign_per_run'] ?? 4) }}টি,
            তাজা {{ $digits($config['collect']['max_age_hours'] ?? 24) }} ঘণ্টা,
            প্রতিবেদন ন্যূনতম {{ $digits($config['collect']['min_body_chars'] ?? 400) }} অক্ষর,
            অনুবাদের বাজেট {{ $digits($config['translate']['max_chars_per_run'] ?? 6000) }} অক্ষর।
            অনুবাদকারী: {{ config('translate.driver') }}।
        </p>
    </div>
</x-app-layout>
