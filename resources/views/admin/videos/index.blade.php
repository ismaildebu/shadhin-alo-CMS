<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ভিডিও গ্যালারি</h2>
    </x-slot>

    <div class="ad-page">

        @if(session('success'))
            <div class="ad-alert ad-alert-ok">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="ad-alert ad-alert-err">{{ $errors->first() }}</div>
        @endif

        <div class="ad-pagehead">
            <h1>ভিডিও গ্যালারি</h1>
            
            <div class="ad-tabs" style="margin-bottom:0;">
                <a href="{{ route('video-channels.index') }}" class="ad-tab">ইউটিউব চ্যানেল</a>
                <a href="{{ route('videos.create') }}" class="ad-btn-solid">+ নতুন ভিডিও</a>
            </div>
        </div>

        <form method="GET" class="ad-search">
            <input type="text" name="q" value="{{ $term }}" placeholder="শিরোনামে খুঁজুন…" class="ad-input">
            <button type="submit" class="ad-btn-dark">খুঁজুন</button>
        </form>

        <div class="ad-panel">
            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                        <tr>
                            <th>ভিডিও</th>
                            <th>অবস্থা</th>
                            <th>কাজ</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($videos as $video)
                        <tr>
                            <td>
                                <div class="ad-news-cell">
                                    <img src="{{ $video->thumbnail }}" alt="" class="ad-vthumb" loading="lazy">
                                    <div>
                                        <div class="ad-news-title">{{ $video->title }}</div>
                                        <div class="ad-news-sub">
                                            #{{ $video->id }}
                                            &middot; {{ $video->published_at?->format('d M Y') ?? '—' }}
                                            &middot; {{ $video->creator->name ?? '—' }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td data-label="অবস্থা">
                                <span class="ad-badge {{ $video->status ? 'st-published' : 'st-draft' }}">
                                    {{ $video->status ? 'প্রকাশিত' : 'লুকানো' }}
                                </span>
                            </td>

                            <td>
                                <div class="ad-actions">
                                    <a href="{{ route('videos.edit', $video) }}" class="ad-act act-edit">সম্পাদনা</a>
                                    <a href="{{ $video->watch_url }}" target="_blank" rel="noopener" class="ad-act">ইউটিউবে দেখুন</a>
                                    <form action="{{ route('videos.destroy', $video) }}" method="POST"
                                          onsubmit="return confirm('ভিডিওটি গ্যালারি থেকে মুছে ফেলতে চান?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ad-act act-del">মুছুন</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="ad-empty">কোনো ভিডিও নেই। প্রথম ভিডিওটি যোগ করুন।</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($videos->hasPages())
                <div class="ad-pager">{{ $videos->links() }}</div>
            @endif
        </div>

    </div>
</x-app-layout>