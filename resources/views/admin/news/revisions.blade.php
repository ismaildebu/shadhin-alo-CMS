<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">সংস্করণ ইতিহাস</h2>
    </x-slot>

    <div class="ad-page">

        <div class="ad-pagehead">
            <h1>সংস্করণ ইতিহাস</h1>
            <a href="{{ route('news.edit', $news) }}" class="ad-tab">← সম্পাদনায় ফিরুন</a>
        </div>

        <p class="ad-note"><b>{{ $news->title }}</b></p>

        <div class="ad-panel">
            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>কখন</th>
                            <th>কে</th>
                            <th>শিরোনাম (তখন)</th>
                            <th>নোট</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($revisions as $revision)
                        <tr>
                            <td data-label="সংস্করণ"><span class="ad-rev-id">{{ $revision->id }}</span></td>
                            <td data-label="কখন">{{ $revision->created_at->timezone(config('app.timezone'))->format('d M Y, h:i A') }}</td>
                            <td data-label="কে">{{ $revision->user->name ?? 'সিস্টেম' }}</td>
                            <td>{{ $revision->snapshot['title'] ?? '' }}</td>
                            <td data-label="নোট">{{ $revision->note }}</td>
                            <td>
                                <form action="{{ route('news.revisions.restore', [$news, $revision]) }}" method="POST"
                                      onsubmit="return confirm('এই সংস্করণের লেখা বর্তমানের জায়গায় বসাতে চান?')">
                                    @csrf
                                    <button type="submit" class="ad-act act-flow">পুনরুদ্ধার</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="ad-empty">এখনো কোনো সংস্করণ সংরক্ষিত হয়নি।</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($revisions->hasPages())
                <div class="ad-pager">{{ $revisions->links() }}</div>
            @endif
        </div>

    </div>
</x-app-layout>