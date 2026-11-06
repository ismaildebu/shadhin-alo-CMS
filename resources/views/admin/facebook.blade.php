<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ফেসবুক পোস্ট</h2>
    </x-slot>

    @php
        $bn = fn (int|string $n): string => \App\Support\BanglaTime::digits($n);
        $oldTicks = old('fb_categories');
        $ticked = $oldTicks !== null
            ? array_map('intval', (array) $oldTicks)
            : $categories->where('fb_auto', true)->pluck('id')->all();
        $count = fn (string $status): int => (int) ($counts[$status] ?? 0);
    @endphp

    <div class="ad-page">

        @if(session('success'))
            <div class="ad-alert ad-alert-ok">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="ad-alert ad-alert-err">{{ $errors->first() }}</div>
        @endif

        <div class="ad-pagehead">
            <h1>ফেসবুক পোস্ট</h1>
        </div>

        <div class="ad-stats">
            <div class="ad-stat">
                <span class="ad-stat-ico tone-amber"><svg class="ad-ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
                <span><div class="ad-stat-value">{{ $bn($count(\App\Models\FacebookPost::NEEDS_CARD)) }}</div><div class="ad-stat-label">কার্ড লাগবে</div></span>
            </div>
            <div class="ad-stat">
                <span class="ad-stat-ico tone-blue"><svg class="ad-ico" viewBox="0 0 24 24"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span>
                <span><div class="ad-stat-value">{{ $bn($count(\App\Models\FacebookPost::QUEUED)) }}</div><div class="ad-stat-label">অপেক্ষমাণ</div></span>
            </div>
            <div class="ad-stat">
                <span class="ad-stat-ico tone-green"><svg class="ad-ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/></svg></span>
                <span><div class="ad-stat-value">{{ $bn($count(\App\Models\FacebookPost::POSTED)) }}</div><div class="ad-stat-label">পোস্ট হয়েছে</div></span>
            </div>
            <div class="ad-stat">
                <span class="ad-stat-ico tone-red"><svg class="ad-ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg></span>
                <span><div class="ad-stat-value">{{ $bn($count(\App\Models\FacebookPost::FAILED)) }}</div><div class="ad-stat-label">ব্যর্থ</div></span>
            </div>
        </div>

        <div class="ad-panel" style="margin-bottom:20px;">
            <div class="ad-panel-head"><h2>কোন পেজে যাচ্ছে</h2></div>
            <div class="ad-panel-body">
                <p class="ad-note" style="margin-bottom:12px;">
                    খবর প্রকাশ পাওয়ামাত্র তার ফটোকার্ডটি পেজের টাইমলাইনে পোস্ট হয়, আর পোস্টের নিচে
                    কমেন্টে খবরের লিংক বসে। টিক না-করা ক্যাটাগরির খবর <strong>পাঠান</strong> বোতামের
                    অপেক্ষায় থাকে; কার্ড না থাকলে লাইনটি "কার্ড লাগবে" অবস্থায় দাঁড়িয়ে থাকে।
                </p>

                <div class="ad-two">
                    <div>
                        <div class="ad-label">পেজ আইডি (.env)</div>
                        <div>{{ $pageId !== '' ? $bn($pageId) : 'বসানো নেই' }}</div>
                        <div class="ad-hint">Graph API: {{ $version }}</div>
                    </div>
                    <div>
                        <div class="ad-label">পেজ টোকেন (.env)</div>
                        <div>{{ $hasToken ? 'বসানো আছে' : 'বসানো নেই' }}</div>
                        <div class="ad-hint">টোকেন লুকানো থাকে — এখান থেকে দেখা বা বদলানো যায় না।</div>
                    </div>
                </div>

                <div style="margin-top:14px;">
                    <span class="ad-badge {{ $ready ? 'st-published' : 'st-off' }}">
                        {{ $ready ? 'পোস্ট চলবে' : 'চালু নয়' }}
                    </span>
                </div>

                <form method="POST" action="{{ route('facebook.verify') }}" style="margin-top:14px;">
                    @csrf
                    <button type="submit" class="ad-btn-solid">টোকেন ও পেজ যাচাই করুন</button>
                </form>
            </div>
        </div>

        <form method="POST" action="{{ route('facebook.update') }}" class="ad-form">
            @csrf
            @method('PUT')

            <div class="ad-panel" style="margin-bottom:20px;">
                <div class="ad-panel-head"><h2>সেটিংস</h2></div>
                <div class="ad-panel-body">

                    <div class="ad-two ad-fgroup">
                        <div>
                            <label class="ad-label" for="fb_enabled">ফেসবুকে অটো পোস্ট</label>
                            <select id="fb_enabled" name="fb_enabled" class="ad-field">
                                <option value="1" @selected(old('fb_enabled', $enabled ? '1' : '0') === '1')>চালু</option>
                                <option value="0" @selected(old('fb_enabled', $enabled ? '1' : '0') === '0')>বন্ধ</option>
                            </select>
                            <p class="ad-hint">বন্ধ থাকলে নতুন প্রকাশিত খবর এই তালিকায় আসে না।</p>
                        </div>
                        <div>
                            <label class="ad-label">টিক করা ক্যাটাগরি</label>
                            <div>{{ $bn($categories->whereStrict('fb_auto', true)->count()) }}টি ক্যাটাগরির খবর আপনি ফেসবুকে যাবে</div>
                            <p class="ad-hint">নিচের তালিকায় টিক দিন; বাকি খবর "পাঠান" বোতামের অপেক্ষায় থাকবে।</p>
                        </div>
                    </div>

                    <div class="ad-fgroup">
                        <label class="ad-label" for="fb_message">পোস্টের লেখা (কার্ডের ক্যাপশন)</label>
                        <textarea id="fb_message" name="fb_message" class="ad-field" rows="4">{{ old('fb_message', $message) }}</textarea>
                        <p class="ad-hint">
                            জায়গামতো বসে: <code>{title}</code> <code>{category}</code> <code>{date}</code>
                            <code>{summary}</code> <code>{url}</code> — ডিফল্ট: <code>{{ $defaults['message'] }}</code>
                        </p>
                    </div>

                    <div class="ad-fgroup">
                        <label class="ad-label" for="fb_comment">কমেন্টের লেখা (সাধারণত লিংক)</label>
                        <textarea id="fb_comment" name="fb_comment" class="ad-field" rows="2">{{ old('fb_comment', $comment) }}</textarea>
                        <p class="ad-hint">ফাঁকা রাখলে কোনো কমেন্ট বসে না। ডিফল্ট: <code>{{ $defaults['comment'] }}</code></p>
                    </div>

                    <div class="ad-fgroup">
                        <label class="ad-label">কোন ক্যাটাগরি অটো পোস্ট হবে</label>
                        <div class="ad-checks">
                            @foreach($categories as $category)
                                <label class="ad-check">
                                    <input type="checkbox" name="fb_categories[]" value="{{ $category->id }}"
                                           @checked(in_array((int) $category->id, $ticked, true))>
                                    {{ $category->name }}
                                </label>
                            @endforeach
                        </div>
                        @error('fb_categories')<p class="ad-hint">{{ $message }}</p>@enderror
                    </div>

                    <button type="submit" class="ad-btn-solid">সেটিংস সংরক্ষণ করুন</button>
                </div>
            </div>
        </form>

        <div class="ad-panel" style="margin-bottom:20px;">
            <div class="ad-panel-head"><h2>অপেক্ষমাণ ও ব্যর্থ</h2></div>
            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                    <tr>
                        <th>খবর</th>
                        <th>ক্যাটাগরি</th>
                        <th>অবস্থা</th>
                        <th>কী হবে</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($waiting as $post)
                        @php $auto = (bool) ($post->news?->category?->fb_auto ?? false); @endphp
                        <tr>
                            <td data-label="খবর">
                                @if($post->news)
                                    <a href="{{ route('news.edit', $post->news) }}">{{ $post->news->title }}</a>
                                    @if($post->news->status !== \App\Models\News::STATUS_PUBLISHED)
                                        <div class="ad-hint">খবরটি এখন প্রকাশিত নয় ({{ $post->news->status_label }})</div>
                                    @endif
                                @else
                                    <span class="ad-hint">খবরটি মুছে ফেলা হয়েছে</span>
                                @endif
                            </td>
                            <td data-label="ক্যাটাগরি">{{ $post->news?->category?->name ?? '—' }}</td>
                            <td data-label="অবস্থা">
                                <span class="ad-badge {{ $post->badge }}">{{ $post->status_label }}</span>
                                @if($post->error !== '')
                                    <div class="ad-hint">{{ $post->error }}</div>
                                @endif
                                @if($post->attempts > 0)
                                    <div class="ad-hint">চেষ্টা: {{ $bn($post->attempts) }} বার</div>
                                @endif
                            </td>
                            <td data-label="কী হবে">
                                @if($post->status === \App\Models\FacebookPost::NEEDS_CARD)
                                    <a href="{{ route('news.card', $post->news_id) }}">কার্ড বানান</a>
                                @elseif($auto)
                                    পরের চালে আপনি যাবে
                                @else
                                    ছাড়পত্র বাকি
                                @endif
                            </td>
                            <td data-label="">
                                <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                                    @if($post->status !== \App\Models\FacebookPost::NEEDS_CARD)
                                        <form method="POST" action="{{ route('facebook.send', $post) }}">
                                            @csrf
                                            <button type="submit" class="ad-tab">পাঠান</button>
                                        </form>
                                    @endif
                                    @if($post->status === \App\Models\FacebookPost::FAILED)
                                        <form method="POST" action="{{ route('facebook.resend', $post) }}">
                                            @csrf
                                            <button type="submit" class="ad-tab">আবার পাঠান</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('facebook.destroy', $post) }}"
                                          onsubmit="return confirm('সারিটি মুছে ফেলবেন? ফেসবুকের পোস্ট এখান থেকে মোছা হয় না।')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ad-act act-del">মুছুন</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="ad-empty">অপেক্ষায় কিছু নেই।</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="ad-panel">
            <div class="ad-panel-head"><h2>সাম্প্রতিক পোস্ট</h2></div>
            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                    <tr>
                        <th>খবর</th>
                        <th>ক্যাটাগরি</th>
                        <th>পোস্ট</th>
                        <th>কখন</th>
                        <th>পাঠিয়েছেন</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($sent as $post)
                        <tr>
                            <td data-label="খবর">
                                @if($post->news)
                                    <a href="{{ route('news.edit', $post->news) }}">{{ $post->news->title }}</a>
                                @else
                                    <span class="ad-hint">খবরটি মুছে ফেলা হয়েছে</span>
                                @endif
                            </td>
                            <td data-label="ক্যাটাগরি">{{ $post->news?->category?->name ?? '—' }}</td>
                            <td data-label="পোস্ট">
                                @if($post->permalink)
                                    <a href="{{ $post->permalink }}" target="_blank" rel="noopener nofollow">ফেসবুকে দেখুন</a>
                                @else
                                    <span class="ad-hint">—</span>
                                @endif
                                @if($post->error !== '')
                                    <div class="ad-hint">{{ $post->error }}</div>
                                @endif
                            </td>
                            <td data-label="কখন">
                                {{ $post->posted_at ? \App\Support\BanglaTime::dateTime($post->posted_at) : '—' }}
                            </td>
                            <td data-label="পাঠিয়েছেন">{{ $post->approver?->name ?? 'আপনি' }}</td>
                            <td data-label="">
                                <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                                    <form method="POST" action="{{ route('facebook.resend', $post) }}"
                                          onsubmit="return confirm('একই খবরটি আবার ফেসবুকে পোস্ট হবে (আগের পোস্ট থাকবে)। এগিয়ে যাবেন?')">
                                        @csrf
                                        <button type="submit" class="ad-tab">আবার পাঠান</button>
                                    </form>
                                    <form method="POST" action="{{ route('facebook.destroy', $post) }}"
                                          onsubmit="return confirm('সারিটি মুছে ফেলবেন? ফেসবুকের পোস্ট এখান থেকে মোছা হয় না।')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ad-act act-del">মুছুন</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="ad-empty">এখনো কিছু পোস্ট হয়নি।</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
