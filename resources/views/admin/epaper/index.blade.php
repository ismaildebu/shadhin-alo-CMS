<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ই-পেপার</h2>
    </x-slot>

    <div class="ad-page">

        @if(session('success'))
            <div class="ad-alert ad-alert-ok">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="ad-alert ad-alert-err">{{ $errors->first() }}</div>
        @endif

        <div class="ad-pagehead">
            <h1>ই-পেপার</h1>
            <a href="{{ route('frontend.epaper.index') }}" target="_blank" rel="noopener" class="ad-btn">সাইটের পাতা দেখুন</a>
        </div>

        <div class="ad-panel" style="margin-bottom:20px;">
            <div class="ad-panel-head">
                <h2>নতুন সংস্করণ</h2>
                <span class="ad-panel-note">প্রতি তারিখে একটি পাতা; আগে থেকে থাকলে সেই পাতাটিই খুলে যাবে।</span>
            </div>
            <div class="ad-panel-body">
                <form action="{{ route('epaper.store') }}" method="POST" class="ep-newform">
                    @csrf
                    <label>
                        <span>তারিখ</span>
                        <input type="date" name="edition_date" value="{{ $today }}" required>
                    </label>
                    <button type="submit" class="ad-btn-solid">পাতা খুলুন</button>
                </form>
            </div>
        </div>

        <div class="ad-panel">
            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                        <tr>
                            <th>তারিখ</th>
                            <th>সংবাদ</th>
                            <th>অবস্থা</th>
                            <th>তৈরি</th>
                            <th>কাজ</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($pages as $page)
                        @php [$stateLabel, $stateClass] = $page->state(); @endphp
                        <tr>
                            <td data-label="তারিখ">
                                <div class="ad-news-title">{{ $page->edition_date->format('d F Y, l') }}</div>
                            </td>
                            <td data-label="সংবাদ"><span class="ad-count">{{ $page->blockCount() }}</span></td>
                            <td data-label="অবস্থা"><span class="ad-badge {{ $stateClass }}">{{ $stateLabel }}</span></td>
                            <td data-label="তৈরি">
                                <div class="ad-news-sub">
                                    {{ $page->creator?->name ?? '—' }} &middot; {{ $page->created_at->format('d M Y') }}
                                </div>
                            </td>
                            <td>
                                <div class="ad-actions">
                                    <a href="{{ route('epaper.edit', $page) }}" class="ad-act act-edit">সাজান</a>
                                    <form action="{{ route('epaper.destroy', $page) }}" method="POST"
                                          onsubmit="return confirm('{{ $page->edition_date->format('d M Y') }} তারিখের পাতা মুছে ফেলতে চান?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ad-act act-del">মুছুন</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="ad-empty">কোনো সংস্করণ নেই। উপরের তারিখ দিয়ে প্রথম পাতাটি খুলুন।</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($pages->hasPages())
                <div class="ad-pager">{{ $pages->links() }}</div>
            @endif
        </div>

    </div>
</x-app-layout>
