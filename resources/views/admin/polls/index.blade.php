<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">অনলাইন ভোট</h2>
    </x-slot>

    <div class="ad-page">

        @if(session('success'))
            <div class="ad-alert ad-alert-ok">{{ session('success') }}</div>
        @endif

        <div class="ad-pagehead">
            <h1>অনলাইন ভোট</h1>
            <a href="{{ route('admin.polls.create') }}" class="ad-tab">+ নতুন ভোট</a>
        </div>

        <p class="ad-note">
            সাইডবারে সবচেয়ে নতুন <b>চালু</b> ভোটটি দেখানো হয়। ভোট বন্ধ করলে সেটি সাইডবার থেকে সরে যায়, তবে ফলাফলসহ আর্কাইভে থাকে।
            প্রকাশের তারিখ ভবিষ্যতের হলে ভোটটি সেই সময়ের আগে কোথাও দেখা যায় না।
        </p>

        <div class="ad-panel">
            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                        <tr>
                            <th>প্রশ্ন</th>
                            <th>অবস্থা</th>
                            <th class="ad-center">মোট ভোট</th>
                            <th>তারিখ</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($polls as $poll)
                            <tr>
                                <td data-label="প্রশ্ন">{{ \Illuminate\Support\Str::limit($poll->question, 90) }}</td>
                                <td data-label="অবস্থা">{{ $poll->isOpen() ? 'চালু' : ($poll->is_active ? 'নির্ধারিত' : 'বন্ধ') }}</td>
                                <td class="ad-center" data-label="মোট ভোট">{{ \App\Support\BanglaTime::digits($poll->totalVotes()) }}</td>
                                <td data-label="তারিখ">{{ \App\Support\BanglaTime::date($poll->shownDate()) }}</td>
                                <td>
                                    <a href="{{ route('frontend.polls.show', $poll) }}" target="_blank" rel="noopener" class="ad-tab">দেখুন</a>
                                    <a href="{{ route('admin.polls.edit', $poll) }}" class="ad-tab">সম্পাদনা</a>
                                    <form method="POST" action="{{ route('admin.polls.destroy', $poll) }}" style="display:inline" onsubmit="return confirm('এই ভোটটি মুছে ফেলবেন?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ad-tab">মুছুন</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if($polls->count() === 0)
            <p class="ad-hint">এখনো কোনো ভোট নেই। ওপরের "নতুন ভোট" দিয়ে শুরু করুন।</p>
        @endif

        <div class="mt-4">{{ $polls->links() }}</div>

    </div>
</x-app-layout>
