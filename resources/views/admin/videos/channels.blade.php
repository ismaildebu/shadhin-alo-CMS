<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ইউটিউব চ্যানেল</h2>
    </x-slot>

    <div class="ad-page">

        @if(session('success'))
            <div class="ad-alert ad-alert-ok">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="ad-alert ad-alert-err">{{ $errors->first() }}</div>
        @endif

        <div class="ad-pagehead">
            <h1>ইউটিউব চ্যানেল</h1>
            <div class="ad-tabs" style="margin-bottom:0;">
                <a href="{{ route('videos.index') }}" class="ad-tab">← ভিডিও তালিকা</a>
                @if($channels->isNotEmpty())
                    <form action="{{ route('video-channels.sync-all') }}" method="POST" style="margin:0;">
                        @csrf
                        <button type="submit" class="ad-btn-solid">সব চ্যানেল এখনই সিঙ্ক করুন</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="ad-panel" style="margin-bottom:20px;">
            <div class="ad-panel-head"><h2>নতুন চ্যানেল যোগ করুন</h2></div>
            <div class="ad-panel-body">
                <form action="{{ route('video-channels.store') }}" method="POST" class="ad-form">
                    @csrf

                    <div class="ad-fgroup">
                        <label class="ad-label">চ্যানেলের লিংক বা আইডি <span class="ad-req">*</span></label>
                        <input type="text" name="channel" value="{{ old('channel') }}" class="ad-field"
                               placeholder="https://www.youtube.com/@চ্যানেলের-নাম" required>
                        <p class="ad-hint">youtube.com/@নাম, youtube.com/channel/UC... অথবা সরাসরি চ্যানেল আইডি (UC দিয়ে শুরু)। যোগ করার সাথে সাথে চ্যানেলের সর্বশেষ ১৫টি ভিডিও আসবে, এরপর নতুন ভিডিও নিজে নিজে আসবে।</p>
                    </div>

                    <div class="ad-fgroup">
                        <label class="ad-check">
                            <input type="checkbox" name="auto_publish" value="1" @checked(old('channel') === null || old('auto_publish'))>
                            নতুন ভিডিও স্বয়ংক্রিয়ভাবে প্রকাশিত হবে
                        </label>
                        <p class="ad-hint">টিক না দিলে ভিডিওগুলো "লুকানো" অবস্থায় আসবে; আপনি ভিডিও তালিকা থেকে দেখে প্রকাশ করবেন।</p>
                    </div>

                    <button type="submit" class="ad-btn-solid">চ্যানেল যোগ করুন</button>
                </form>
            </div>
        </div>

        <div class="ad-panel">
            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                        <tr>
                            <th>চ্যানেল</th>
                            <th class="ad-num">ভিডিও</th>
                            <th>সর্বশেষ সিঙ্ক</th>
                            <th>অবস্থা</th>
                            <th>কাজ</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($channels as $channel)
                        <tr>
                            <td>
                                <div class="ad-tag-name">{{ $channel->name }}</div>
                                <div class="ad-news-sub"><span class="ad-slug">{{ $channel->channel_id }}</span></div>
                            </td>
                            <td class="ad-num" data-label="ভিডিও"><span class="ad-count">{{ $channel->videos_count }}</span></td>
                            <td data-label="সর্বশেষ সিঙ্ক">
                                {{ $channel->last_synced_at?->timezone(config('app.timezone'))->format('d M, h:i A') ?? 'এখনো হয়নি' }}
                            </td>
                            <td data-label="অবস্থা">
                                @if($channel->last_error)
                                    <span class="ad-badge st-off">সমস্যা</span>
                                    <div class="ad-field-error">{{ $channel->last_error }}</div>
                                @else
                                    <span class="ad-badge st-published">ঠিক আছে</span>
                                @endif
                                <div class="ad-news-sub">{{ $channel->auto_publish ? 'স্বয়ংক্রিয় প্রকাশ' : 'লুকানো অবস্থায় আসে' }}</div>
                            </td>
                            <td>
                                <div class="ad-actions">
                                    <form action="{{ route('video-channels.sync', $channel) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="ad-act act-flow">এখনই সিঙ্ক</button>
                                    </form>
                                    <a href="{{ $channel->url }}" target="_blank" rel="noopener" class="ad-act">চ্যানেল দেখুন</a>
                                    <form action="{{ route('video-channels.destroy', $channel) }}" method="POST"
                                          onsubmit="return confirm('চ্যানেলটি সরাতে চান? আগে আনা ভিডিওগুলো গ্যালারিতে থেকে যাবে।')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ad-act act-del">সরান</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="ad-empty">কোনো চ্যানেল যোগ করা নেই।</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</x-app-layout>